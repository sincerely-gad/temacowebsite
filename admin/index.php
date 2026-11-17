<?php
declare(strict_types=1);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const ROOT_DIR = __DIR__ . '/..';
const DATA_DIR = ROOT_DIR . '/data';
const UPLOAD_DIR = ROOT_DIR . '/uploads';
const PRODUCT_DATA = DATA_DIR . '/products.json';
const BLOG_DATA = DATA_DIR . '/blog-posts.json';
const PROJECT_DATA = DATA_DIR . '/projects.json';
const OVERRIDE_DATA = DATA_DIR . '/content-overrides.json';
const CREDENTIAL_FILE = __DIR__ . '/.credentials.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ensure_storage(): void
{
    $directories = [DATA_DIR, UPLOAD_DIR, UPLOAD_DIR . '/products', UPLOAD_DIR . '/blog', UPLOAD_DIR . '/projects', UPLOAD_DIR . '/overrides'];
    foreach ($directories as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('The content storage folders could not be created.');
        }
    }
    foreach ([PRODUCT_DATA, BLOG_DATA, PROJECT_DATA] as $file) {
        if (!is_file($file) && file_put_contents($file, "[]\n", LOCK_EX) === false) {
            throw new RuntimeException('The content data files could not be created.');
        }
    }
    if (!is_file(OVERRIDE_DATA) && file_put_contents(OVERRIDE_DATA, "{\n    \"products\": {},\n    \"blogs\": {}\n}\n", LOCK_EX) === false) {
        throw new RuntimeException('The image override data file could not be created.');
    }
}

function read_records(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? array_values($decoded) : [];
}

function write_records(string $file, array $records): void
{
    $json = json_encode(array_values($records), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($file, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('The content could not be saved. Check that the data folder is writable.');
    }
}

function read_overrides(): array
{
    $defaults = ['products' => [], 'blogs' => []];
    if (!is_file(OVERRIDE_DATA)) {
        return $defaults;
    }
    $decoded = json_decode((string) file_get_contents(OVERRIDE_DATA), true);
    if (!is_array($decoded)) {
        return $defaults;
    }
    return [
        'products' => is_array($decoded['products'] ?? null) ? $decoded['products'] : [],
        'blogs' => is_array($decoded['blogs'] ?? null) ? $decoded['blogs'] : [],
    ];
}

function write_overrides(array $overrides): void
{
    $json = json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents(OVERRIDE_DATA, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('The image override could not be saved. Check that the data folder is writable.');
    }
}

function slugify(string $value): string
{
    $value = trim(function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value));
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'entry-' . time();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $submitted = (string) ($_POST['csrf'] ?? '');
    if ($submitted === '' || !hash_equals(csrf_token(), $submitted)) {
        throw new RuntimeException('Your session expired. Refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function redirect_admin(string $anchor = ''): void
{
    header('Location: index.php' . $anchor);
    exit;
}

function is_authenticated(): bool
{
    return !empty($_SESSION['admin_authenticated']);
}

function credential_hash(): ?string
{
    if (!is_file(CREDENTIAL_FILE)) {
        return null;
    }
    $hash = require CREDENTIAL_FILE;
    return is_string($hash) && $hash !== '' ? $hash : null;
}

function save_credential(string $password): void
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $contents = "<?php\nreturn " . var_export($hash, true) . ";\n";
    if (file_put_contents(CREDENTIAL_FILE, $contents, LOCK_EX) === false) {
        throw new RuntimeException('The admin password could not be saved. Check folder permissions.');
    }
}

function normalized_uploads(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }
    if (!is_array($files['name'])) {
        return [$files];
    }
    $normalized = [];
    foreach ($files['name'] as $index => $name) {
        $normalized[] = [
            'name' => $name,
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }
    return $normalized;
}

function store_upload(array $file, string $folder, bool $allowPdf = false): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('One of the selected files could not be uploaded.');
    }
    if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
        throw new RuntimeException('Each uploaded file must be 8 MB or smaller.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if ($allowPdf) {
        $allowed['application/pdf'] = 'pdf';
    }
    if (!isset($allowed[$mime])) {
        throw new RuntimeException($allowPdf
            ? 'Only JPG, PNG, WebP, GIF, or PDF files are accepted.'
            : 'Only JPG, PNG, WebP, or GIF images are accepted.');
    }

    $extension = $allowed[$mime];
    $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $targetDir = UPLOAD_DIR . '/' . $folder;
    $target = $targetDir . '/' . $filename;
    if (!move_uploaded_file($tmpName, $target)) {
        throw new RuntimeException('An uploaded file could not be stored.');
    }
    return 'uploads/' . $folder . '/' . $filename;
}

function find_record(array $records, int $id): ?array
{
    foreach ($records as $record) {
        if ((int) ($record['id'] ?? 0) === $id) {
            return $record;
        }
    }
    return null;
}

function product_new_expiry(array $product): ?int
{
    if (empty($product['isNew'])) {
        return null;
    }
    $storedExpiry = strtotime((string) ($product['newUntil'] ?? ''));
    if ($storedExpiry !== false) {
        return $storedExpiry;
    }
    $createdAt = strtotime((string) ($product['createdAt'] ?? ''));
    return $createdAt !== false ? $createdAt + (30 * 86400) : null;
}

function product_is_new(array $product): bool
{
    $expiry = product_new_expiry($product);
    return $expiry !== null && $expiry > time();
}

try {
    ensure_storage();
} catch (Throwable $error) {
    $storageError = $error->getMessage();
}

$credentialsExist = credential_hash() !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($storageError)) {
    try {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'setup' && !$credentialsExist) {
            $password = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirmation'] ?? '');
            if (strlen($password) < 12) {
                throw new RuntimeException('Use a password with at least 12 characters.');
            }
            if (!hash_equals($password, $confirmation)) {
                throw new RuntimeException('The password confirmation does not match.');
            }
            save_credential($password);
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            flash('success', 'Admin access is ready. You can now publish content.');
            redirect_admin('#dashboard');
        }

        if ($action === 'login' && $credentialsExist) {
            $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
            if ($lockedUntil > time()) {
                throw new RuntimeException('Too many attempts. Wait a few minutes and try again.');
            }
            $password = (string) ($_POST['password'] ?? '');
            if (!password_verify($password, (string) credential_hash())) {
                $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
                $_SESSION['login_attempts'] = $attempts;
                if ($attempts >= 5) {
                    $_SESSION['login_locked_until'] = time() + 300;
                    $_SESSION['login_attempts'] = 0;
                }
                throw new RuntimeException('The password is incorrect.');
            }
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            redirect_admin('#dashboard');
        }

        if ($action === 'logout') {
            $_SESSION = [];
            session_destroy();
            redirect_admin();
        }

        if (!is_authenticated()) {
            throw new RuntimeException('Sign in before changing website content.');
        }

        if ($action === 'save_product') {
            $records = read_records(PRODUCT_DATA);
            $recordId = (int) ($_POST['record_id'] ?? 0);
            $existing = $recordId > 0 ? find_record($records, $recordId) : null;
            $name = trim((string) ($_POST['name'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $sku = trim((string) ($_POST['sku'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($name === '' || $category === '' || $sku === '' || $description === '') {
                throw new RuntimeException('Product name, category, SKU, and description are required.');
            }
            foreach ($records as $record) {
                if ((int) ($record['id'] ?? 0) !== $recordId && strcasecmp((string) ($record['sku'] ?? ''), $sku) === 0) {
                    throw new RuntimeException('That SKU is already used by another panel-managed product.');
                }
            }

            $gallery = is_array($existing['gallery'] ?? null) ? $existing['gallery'] : [];
            $galleryUploads = normalized_uploads($_FILES['gallery'] ?? []);
            if (count($galleryUploads) > 8 || count($gallery) + count(array_filter($galleryUploads, static fn(array $file): bool => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) > 8) {
                throw new RuntimeException('A product can have a maximum of 8 gallery images.');
            }
            foreach ($galleryUploads as $index => $file) {
                $path = store_upload($file, 'products');
                if ($path) {
                    $gallery[] = ['url' => $path, 'label' => $name . ' image ' . (count($gallery) + 1)];
                }
            }
            if (!$gallery) {
                throw new RuntimeException('Upload at least one product image.');
            }

            $datasheet = (string) ($existing['datasheet'] ?? '');
            $uploadedDatasheet = store_upload($_FILES['datasheet'] ?? [], 'products', true);
            if ($uploadedDatasheet) {
                $datasheet = $uploadedDatasheet;
            }

            $specNames = $_POST['spec_name'] ?? [];
            $specValues = $_POST['spec_value'] ?? [];
            $specs = [];
            if (is_array($specNames) && is_array($specValues)) {
                foreach ($specNames as $index => $specName) {
                    $specName = trim((string) $specName);
                    $specValue = trim((string) ($specValues[$index] ?? ''));
                    if ($specName !== '' && $specValue !== '') {
                        $specs[] = [$specName, $specValue];
                    }
                }
            }

            $isNew = isset($_POST['is_new']);
            $featureHome = isset($_POST['feature_home']);
            $createdAt = (string) ($existing['createdAt'] ?? gmdate('c'));
            $existingExpiry = $existing ? product_new_expiry($existing) : null;
            $newUntil = null;
            if ($isNew) {
                $newUntil = $existing && product_is_new($existing) && $existingExpiry !== null
                    ? gmdate('c', $existingExpiry)
                    : gmdate('c', time() + (30 * 86400));
            }
            $product = [
                'id' => $recordId > 0 ? $recordId : (time() * 100 + random_int(10, 99)),
                'name' => $name,
                'category' => $category,
                'sku' => $sku,
                'price' => trim((string) ($_POST['price'] ?? '-')) ?: '-',
                'priceNote' => trim((string) ($_POST['price_note'] ?? '')),
                'badge' => $isNew ? 'New Product' : '',
                'isNew' => $isNew,
                'newUntil' => $newUntil,
                'featureHome' => $featureHome,
                'gallery' => $gallery,
                'description' => $description,
                'specs' => $specs,
                'datasheet' => $datasheet !== '' ? $datasheet : $gallery[0]['url'],
                'createdAt' => $createdAt,
                'updatedAt' => gmdate('c'),
            ];

            $replaced = false;
            foreach ($records as $index => $record) {
                if ((int) ($record['id'] ?? 0) === $recordId && $recordId > 0) {
                    $records[$index] = $product;
                    $replaced = true;
                    break;
                }
            }
            if (!$replaced) {
                array_unshift($records, $product);
            }
            write_records(PRODUCT_DATA, $records);
            flash('success', $replaced ? 'Product updated successfully.' : 'New product published to the catalogue.');
            redirect_admin('#products');
        }

        if ($action === 'save_blog') {
            $records = read_records(BLOG_DATA);
            $recordId = (int) ($_POST['record_id'] ?? 0);
            $existing = $recordId > 0 ? find_record($records, $recordId) : null;
            $title = trim((string) ($_POST['title'] ?? ''));
            $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
            $content = trim((string) ($_POST['content'] ?? ''));
            $author = trim((string) ($_POST['author'] ?? 'TEMACO Team')) ?: 'TEMACO Team';
            $publishedAt = trim((string) ($_POST['published_at'] ?? date('Y-m-d')));
            if ($title === '' || $excerpt === '' || $content === '') {
                throw new RuntimeException('Blog title, excerpt, and article content are required.');
            }
            $slug = slugify(trim((string) ($_POST['slug'] ?? '')) ?: $title);
            foreach ($records as $record) {
                if ((int) ($record['id'] ?? 0) !== $recordId && (string) ($record['slug'] ?? '') === $slug) {
                    $slug .= '-' . random_int(10, 99);
                    break;
                }
            }

            $image = (string) ($existing['image'] ?? '');
            $uploadedImage = store_upload($_FILES['image'] ?? [], 'blog');
            if ($uploadedImage) {
                $image = $uploadedImage;
            }
            if ($image === '') {
                throw new RuntimeException('Upload a featured image for the blog post.');
            }

            $post = [
                'id' => $recordId > 0 ? $recordId : (time() * 100 + random_int(10, 99)),
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $excerpt,
                'content' => $content,
                'author' => $author,
                'publishedAt' => $publishedAt,
                'image' => $image,
                'featureHome' => isset($_POST['feature_home']),
                'createdAt' => (string) ($existing['createdAt'] ?? gmdate('c')),
                'updatedAt' => gmdate('c'),
            ];

            $replaced = false;
            foreach ($records as $index => $record) {
                if ((int) ($record['id'] ?? 0) === $recordId && $recordId > 0) {
                    $records[$index] = $post;
                    $replaced = true;
                    break;
                }
            }
            if (!$replaced) {
                array_unshift($records, $post);
            }
            write_records(BLOG_DATA, $records);
            flash('success', $replaced ? 'Blog post updated successfully.' : 'New blog post published.');
            redirect_admin('#blogs');
        }

        if ($action === 'save_project') {
            $records = read_records(PROJECT_DATA);
            $recordId = (int) ($_POST['record_id'] ?? 0);
            $existing = $recordId > 0 ? find_record($records, $recordId) : null;
            $name = trim((string) ($_POST['name'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? 'Ongoing')) ?: 'Ongoing';
            $location = trim((string) ($_POST['location'] ?? ''));
            $scope = trim((string) ($_POST['scope'] ?? ''));
            $delivery = trim((string) ($_POST['delivery'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($name === '' || $category === '' || $location === '' || $scope === '' || $delivery === '' || $description === '') {
                throw new RuntimeException('Project name, category, location, scope, delivery update, and description are required.');
            }

            $slug = slugify(trim((string) ($_POST['slug'] ?? '')) ?: $name);
            foreach ($records as $record) {
                if ((int) ($record['id'] ?? 0) !== $recordId && (string) ($record['slug'] ?? '') === $slug) {
                    $slug .= '-' . random_int(10, 99);
                    break;
                }
            }

            $gallery = is_array($existing['gallery'] ?? null) ? $existing['gallery'] : [];
            $galleryUploads = normalized_uploads($_FILES['gallery'] ?? []);
            $newUploadCount = count(array_filter($galleryUploads, static fn(array $file): bool => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
            if (count($galleryUploads) > 12 || count($gallery) + $newUploadCount > 12) {
                throw new RuntimeException('A project can have a maximum of 12 gallery images.');
            }
            foreach ($galleryUploads as $file) {
                $path = store_upload($file, 'projects');
                if ($path) {
                    $gallery[] = ['url' => $path, 'label' => $name . ' project image ' . (count($gallery) + 1)];
                }
            }
            if (!$gallery) {
                throw new RuntimeException('Upload at least one project image.');
            }

            $project = [
                'id' => $recordId > 0 ? $recordId : (time() * 100 + random_int(10, 99)),
                'name' => $name,
                'slug' => $slug,
                'category' => $category,
                'status' => $status,
                'location' => $location,
                'scope' => $scope,
                'delivery' => $delivery,
                'description' => $description,
                'gallery' => $gallery,
                'featureHome' => isset($_POST['feature_home']),
                'createdAt' => (string) ($existing['createdAt'] ?? gmdate('c')),
                'updatedAt' => gmdate('c'),
            ];

            $replaced = false;
            foreach ($records as $index => $record) {
                if ((int) ($record['id'] ?? 0) === $recordId && $recordId > 0) {
                    $records[$index] = $project;
                    $replaced = true;
                    break;
                }
            }
            if (!$replaced) {
                array_unshift($records, $project);
            }
            write_records(PROJECT_DATA, $records);
            flash('success', $replaced ? 'Project updated successfully.' : 'New project published to the website.');
            redirect_admin('#projects');
        }

        if ($action === 'save_image_override') {
            $type = (string) ($_POST['override_type'] ?? '');
            $identifier = trim((string) ($_POST['identifier'] ?? ''));
            if ($type === 'product') {
                $identifier = strtoupper($identifier);
                if ($identifier === '' || strlen($identifier) > 100) {
                    throw new RuntimeException('Enter the existing product SKU exactly as it appears in the catalogue.');
                }
                $bucket = 'products';
            } elseif ($type === 'blog') {
                $identifier = strtolower(basename((string) parse_url($identifier, PHP_URL_PATH)));
                if (!preg_match('/^blog-details[0-9]*\.html$/', $identifier)) {
                    throw new RuntimeException('Choose a valid existing blog page.');
                }
                $bucket = 'blogs';
            } else {
                throw new RuntimeException('Choose whether this image belongs to an existing product or blog post.');
            }

            $image = store_upload($_FILES['image'] ?? [], 'overrides');
            if ($image === null) {
                throw new RuntimeException('Choose a replacement image.');
            }
            $overrides = read_overrides();
            $overrides[$bucket][$identifier] = [
                'image' => $image,
                'updatedAt' => gmdate('c'),
            ];
            write_overrides($overrides);
            flash('success', $type === 'product'
                ? 'The existing product image was updated.'
                : 'The existing blog image was updated.');
            redirect_admin('#image-fixes');
        }

        if ($action === 'delete_image_override') {
            $type = (string) ($_POST['override_type'] ?? '');
            $identifier = trim((string) ($_POST['identifier'] ?? ''));
            $bucket = $type === 'product' ? 'products' : ($type === 'blog' ? 'blogs' : '');
            if ($bucket === '') {
                throw new RuntimeException('The image override type is invalid.');
            }
            $identifier = $type === 'product' ? strtoupper($identifier) : strtolower($identifier);
            $overrides = read_overrides();
            unset($overrides[$bucket][$identifier]);
            write_overrides($overrides);
            flash('success', 'The replacement image was removed.');
            redirect_admin('#image-fixes');
        }

        if ($action === 'delete_product' || $action === 'delete_blog' || $action === 'delete_project') {
            $file = PROJECT_DATA;
            if ($action === 'delete_product') {
                $file = PRODUCT_DATA;
            } elseif ($action === 'delete_blog') {
                $file = BLOG_DATA;
            }
            $records = read_records($file);
            $recordId = (int) ($_POST['record_id'] ?? 0);
            $records = array_values(array_filter($records, static fn(array $record): bool => (int) ($record['id'] ?? 0) !== $recordId));
            write_records($file, $records);
            $messages = [
                'delete_product' => 'Product removed from the catalogue.',
                'delete_blog' => 'Blog post removed.',
                'delete_project' => 'Project removed from the website.',
            ];
            $anchors = ['delete_product' => '#products', 'delete_blog' => '#blogs', 'delete_project' => '#projects'];
            flash('success', $messages[$action]);
            redirect_admin($anchors[$action]);
        }
    } catch (Throwable $error) {
        flash('error', $error->getMessage());
        redirect_admin();
    }
}

$products = empty($storageError) ? read_records(PRODUCT_DATA) : [];
$posts = empty($storageError) ? read_records(BLOG_DATA) : [];
$projects = empty($storageError) ? read_records(PROJECT_DATA) : [];
$overrides = empty($storageError) ? read_overrides() : ['products' => [], 'blogs' => []];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$authenticated = is_authenticated();
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title>TEMACO Content Panel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/admin.css?v=1">
  <link rel="stylesheet" href="assets/admin-overrides.css?v=2">
</head>
<body>
<?php if (!$credentialsExist || !$authenticated): ?>
  <header class="admin-header auth-header">
    <a class="brand-logo" href="../index.html" aria-label="TEMACO home"><img src="../img/Logo.jpg" alt="TEMACO logo"></a>
    <a class="auth-home-link" href="../index.html">View website</a>
  </header>
  <main class="auth-shell">
    <section class="auth-card">
      <a class="auth-card-logo" href="../index.html" aria-label="TEMACO home"><img src="../img/Logo.jpg" alt="TEMACO logo"></a>
      <p class="eyebrow">Website content panel</p>
      <h1><?= !$credentialsExist ? 'Create Admin Access' : 'Welcome Back' ?></h1>
      <p class="auth-copy"><?= !$credentialsExist
          ? 'Set the private password that will protect product and blog publishing.'
          : 'Sign in to publish products, project galleries, and blog stories.' ?></p>
      <?php if (!empty($storageError)): ?><div class="notice error"><?= e($storageError) ?></div><?php endif; ?>
      <?php if ($flash): ?><div class="notice <?= e((string) $flash['type']) ?>"><?= e((string) $flash['message']) ?></div><?php endif; ?>
      <?php if (empty($storageError)): ?>
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= !$credentialsExist ? 'setup' : 'login' ?>">
        <label>Password
          <input type="password" name="password" minlength="12" autocomplete="<?= !$credentialsExist ? 'new-password' : 'current-password' ?>" required autofocus>
        </label>
        <?php if (!$credentialsExist): ?>
        <label>Confirm password
          <input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required>
        </label>
        <p class="field-hint">Use at least 12 characters. This setup screen disappears after the password is created.</p>
        <?php endif; ?>
        <button class="primary-button" type="submit"><?= !$credentialsExist ? 'Create Secure Panel' : 'Sign In' ?> <span>→</span></button>
      </form>
      <?php endif; ?>
      <a class="back-link" href="../index.html">← Return to website</a>
    </section>
  </main>
<?php else: ?>
  <header class="admin-header">
    <a class="brand-logo" href="../index.html" aria-label="TEMACO home"><img src="../img/Logo.jpg" alt="TEMACO logo"></a>
    <nav aria-label="Panel sections">
      <a href="#dashboard">Overview</a>
      <a href="#products">Products</a>
      <a href="#projects">Projects</a>
      <a href="#blogs">Blog</a>
      <a href="#image-fixes">Image fixes</a>
      <a href="../index.html" target="_blank" rel="noopener">View website ↗</a>
    </nav>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="logout">
      <button class="text-button" type="submit">Sign out</button>
    </form>
  </header>

  <main class="admin-main" id="dashboard">
    <?php if ($flash): ?><div class="notice <?= e((string) $flash['type']) ?>"><?= e((string) $flash['message']) ?></div><?php endif; ?>
    <section class="dashboard-intro">
      <div>
        <p class="eyebrow">Content overview</p>
        <h1>Manage the TEMACO website</h1>
        <p>Add catalogue-ready products, publish project galleries, and share stories without editing website code.</p>
      </div>
      <div class="metric-grid">
        <article><strong><?= count($products) ?></strong><span>Panel products</span></article>
        <article><strong><?= count($projects) ?></strong><span>Panel projects</span></article>
        <article><strong><?= count($posts) ?></strong><span>Panel posts</span></article>
      </div>
    </section>

    <section class="editor-section" id="products">
      <div class="section-heading">
        <div><p class="eyebrow">Catalogue manager</p><h2>Add or update a product</h2></div>
        <a href="../Catalogue1.html" target="_blank" rel="noopener">Open catalogue ↗</a>
      </div>
      <div class="editor-layout">
        <form class="content-form" id="product-form" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_product">
          <input type="hidden" name="record_id" value="">
          <div class="form-grid two">
            <label>Product name <span>*</span><input name="name" required></label>
            <label>Category <span>*</span><input name="category" list="product-categories" required placeholder="e.g. Pavers"></label>
            <datalist id="product-categories">
              <option value="Pavers"><option value="Tiles"><option value="Drainage"><option value="Ventilation"><option value="Kerbs"><option value="Paving Bricks"><option value="Blocks"><option value="Cladding"><option value="Cast Stones"><option value="Terrazzo"><option value="Planters"><option value="Nursery & Plants"><option value="Indigenous Species">
            </datalist>
            <label>SKU <span>*</span><input name="sku" required placeholder="TMC-PRODUCT-01"></label>
            <label>Price or label<input name="price" value="-" placeholder="RWF 25,000 or Contact us"></label>
            <label>Price note<input name="price_note" placeholder="per m², VAT excluded"></label>
            <label class="checkbox-label"><input type="checkbox" name="feature_home" value="1"><span><strong>Show on homepage</strong><small>Adds this product to the Our Products section on the homepage.</small></span></label>
            <label class="checkbox-label"><input type="checkbox" name="is_new" value="1"><span><strong>Mark as a new product</strong><small>Shows the “New Product” badge for 30 days, then removes it automatically.</small></span></label>
          </div>
          <label>Description <span>*</span><textarea name="description" rows="5" required placeholder="What the product is, where it is used, and its main benefit."></textarea></label>
          <div class="upload-grid">
            <label class="upload-field">Product gallery <span>*</span><input type="file" name="gallery[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple><small>Upload up to 8 JPG, PNG, WebP, or GIF images. Existing images remain when editing.</small></label>
            <label class="upload-field">Datasheet<input type="file" name="datasheet" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf"><small>Optional image or PDF, maximum 8 MB.</small></label>
          </div>
          <fieldset class="spec-fieldset">
            <legend>Technical specifications</legend>
            <div id="spec-rows"></div>
            <button class="secondary-button" id="add-spec" type="button">+ Add specification</button>
          </fieldset>
          <div class="form-actions"><button class="primary-button" type="submit">Publish Product <span>→</span></button><button class="text-button reset-editor" type="button">Clear form</button></div>
        </form>
        <aside class="managed-list">
          <h3>Panel-managed products</h3>
          <?php if (!$products): ?><p class="empty-state">Products added through this panel will appear here.</p><?php endif; ?>
          <?php foreach ($products as $product): ?>
          <article class="managed-item">
            <img src="../<?= e((string) ($product['gallery'][0]['url'] ?? 'img/logo2.jpg')) ?>" alt="">
            <div><strong><?= e((string) ($product['name'] ?? 'Product')) ?></strong><span><?= e((string) ($product['category'] ?? '')) ?> · <?= e((string) ($product['sku'] ?? '')) ?></span><?php if (!empty($product['featureHome'])): ?><em>Homepage</em><?php endif; ?><?php if (product_is_new($product)): ?><em>New until <?= e(gmdate('d M Y', (int) product_new_expiry($product))) ?></em><?php endif; ?></div>
            <button class="icon-button edit-product" type="button" data-id="<?= (int) ($product['id'] ?? 0) ?>">Edit</button>
            <form method="post" class="delete-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="record_id" value="<?= (int) ($product['id'] ?? 0) ?>"><button class="icon-button danger" type="submit">Remove</button></form>
          </article>
          <?php endforeach; ?>
        </aside>
      </div>
    </section>

    <section class="editor-section" id="projects">
      <div class="section-heading">
        <div><p class="eyebrow">Project manager</p><h2>Publish a project gallery</h2></div>
        <a href="../index.html#projects" target="_blank" rel="noopener">Open projects ↗</a>
      </div>
      <div class="editor-layout">
        <form class="content-form" id="project-form" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_project">
          <input type="hidden" name="record_id" value="">
          <div class="form-grid two">
            <label>Project name <span>*</span><input name="name" required placeholder="e.g. Kigali Green Corridor"></label>
            <label>URL name<input name="slug" placeholder="Generated automatically from project name"></label>
            <label>Category <span>*</span><input name="category" list="project-categories" required placeholder="e.g. Landscape Construction"></label>
            <datalist id="project-categories">
              <option value="Infrastructure"><option value="Landscape Construction"><option value="Hardscape"><option value="Softscape"><option value="Commercial"><option value="Residential"><option value="Education"><option value="Stadium">
            </datalist>
            <label>Project status <span>*</span><select name="status" required><option value="Ongoing">Ongoing</option><option value="Completed">Completed</option><option value="Planned">Planned</option></select></label>
            <label>Location <span>*</span><input name="location" required placeholder="Kigali, Rwanda"></label>
            <label>Scope of work <span>*</span><input name="scope" required placeholder="Paving, kerbs and landscaping"></label>
            <label>Delivery or progress update <span>*</span><input name="delivery" required placeholder="65% complete · Expected December 2026"></label>
            <label class="checkbox-label"><input type="checkbox" name="feature_home" value="1" checked><span><strong>Show on homepage</strong><small>Adds this project to the Our Projects slider.</small></span></label>
          </div>
          <label>Project description <span>*</span><textarea name="description" rows="6" maxlength="1200" required placeholder="Describe the client need, TEMACO's work, the materials used, and the expected impact."></textarea></label>
          <label class="upload-field">Project gallery <span>*</span><input type="file" name="gallery[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple><small>Upload up to 12 images. The first image becomes the homepage cover. Existing images remain when editing.</small></label>
          <div class="form-actions"><button class="primary-button" type="submit">Publish Project <span>→</span></button><button class="text-button reset-editor" type="button">Clear form</button></div>
        </form>
        <aside class="managed-list">
          <h3>Panel-managed projects</h3>
          <?php if (!$projects): ?><p class="empty-state">Projects published through this panel will appear here.</p><?php endif; ?>
          <?php foreach ($projects as $project): ?>
          <article class="managed-item">
            <img src="../<?= e((string) ($project['gallery'][0]['url'] ?? 'img/logo2.jpg')) ?>" alt="">
            <div><strong><?= e((string) ($project['name'] ?? 'Project')) ?></strong><span><?= e((string) ($project['location'] ?? '')) ?> · <?= e((string) ($project['status'] ?? '')) ?></span><?php if (!empty($project['featureHome'])): ?><em>Homepage</em><?php endif; ?></div>
            <button class="icon-button edit-project" type="button" data-id="<?= (int) ($project['id'] ?? 0) ?>">Edit</button>
            <form method="post" class="delete-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_project"><input type="hidden" name="record_id" value="<?= (int) ($project['id'] ?? 0) ?>"><button class="icon-button danger" type="submit">Remove</button></form>
          </article>
          <?php endforeach; ?>
        </aside>
      </div>
    </section>

    <section class="editor-section" id="blogs">
      <div class="section-heading">
        <div><p class="eyebrow">Blog manager</p><h2>Publish a new story</h2></div>
        <a href="../blog.html" target="_blank" rel="noopener">Open blog ↗</a>
      </div>
      <div class="editor-layout">
        <form class="content-form" id="blog-form" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_blog">
          <input type="hidden" name="record_id" value="">
          <div class="form-grid two">
            <label>Article title <span>*</span><input name="title" required></label>
            <label>URL name<input name="slug" placeholder="Generated automatically from title"></label>
            <label>Author<input name="author" value="TEMACO Team"></label>
            <label>Publish date <span>*</span><input type="date" name="published_at" value="<?= e(date('Y-m-d')) ?>" required></label>
            <label class="checkbox-label"><input type="checkbox" name="feature_home" value="1" checked><span><strong>Feature on homepage</strong><small>Prioritizes this article below the team section.</small></span></label>
          </div>
          <label>Short excerpt <span>*</span><textarea name="excerpt" rows="3" maxlength="420" required placeholder="A concise summary shown on cards."></textarea></label>
          <label>Article content <span>*</span><textarea name="content" rows="14" required placeholder="Write the complete article. Use blank lines to separate paragraphs."></textarea></label>
          <label class="upload-field">Featured image <span>*</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"><small>Landscape images work best. Existing image remains when editing.</small></label>
          <div class="form-actions"><button class="primary-button" type="submit">Publish Blog Post <span>→</span></button><button class="text-button reset-editor" type="button">Clear form</button></div>
        </form>
        <aside class="managed-list">
          <h3>Panel-managed posts</h3>
          <?php if (!$posts): ?><p class="empty-state">Blog posts published through this panel will appear here.</p><?php endif; ?>
          <?php foreach ($posts as $post): ?>
          <article class="managed-item">
            <img src="../<?= e((string) ($post['image'] ?? 'img/logo2.jpg')) ?>" alt="">
            <div><strong><?= e((string) ($post['title'] ?? 'Blog post')) ?></strong><span><?= e((string) ($post['publishedAt'] ?? '')) ?> · <?= e((string) ($post['author'] ?? '')) ?></span><?php if (!empty($post['featureHome'])): ?><em>Homepage</em><?php endif; ?></div>
            <button class="icon-button edit-blog" type="button" data-id="<?= (int) ($post['id'] ?? 0) ?>">Edit</button>
            <form method="post" class="delete-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_blog"><input type="hidden" name="record_id" value="<?= (int) ($post['id'] ?? 0) ?>"><button class="icon-button danger" type="submit">Remove</button></form>
          </article>
          <?php endforeach; ?>
        </aside>
      </div>
    </section>

    <section class="editor-section" id="image-fixes">
      <div class="section-heading">
        <div><p class="eyebrow">Existing content</p><h2>Repair a missing image</h2></div>
        <p class="section-note">This changes only the displayed image. The original hard-coded content remains untouched.</p>
      </div>
      <div class="editor-layout">
        <div class="override-forms">
          <form class="content-form override-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_image_override">
            <input type="hidden" name="override_type" value="product">
            <div>
              <p class="eyebrow">Existing product</p>
              <h3>Replace a catalogue image</h3>
              <p class="field-hint">Copy the product SKU exactly from the catalogue. Saving the same SKU again replaces its previous override.</p>
            </div>
            <label>Product SKU <span>*</span><input name="identifier" maxlength="100" required placeholder="e.g. TP6 or TDS1"></label>
            <label class="upload-field">Replacement image <span>*</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required><small>JPG, PNG, WebP, or GIF, maximum 8 MB.</small></label>
            <div class="form-actions"><button class="primary-button" type="submit">Update Product Image <span>&rarr;</span></button></div>
          </form>

          <form class="content-form override-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_image_override">
            <input type="hidden" name="override_type" value="blog">
            <div>
              <p class="eyebrow">Existing blog post</p>
              <h3>Replace a story image</h3>
              <p class="field-hint">The replacement is used on the blog listing, homepage highlight, and the selected story page.</p>
            </div>
            <label>Existing story <span>*</span>
              <select name="identifier" required>
                <option value="">Choose a story</option>
                <option value="blog-details.html">Sanitation Center Rwamagana</option>
                <option value="blog-details1.html">Sanitation Center Rwamagana (alternate page)</option>
                <option value="blog-details2.html">Award-Winning Projects</option>
                <option value="blog-details3.html">TEMACO Builders Start-Up Story</option>
                <option value="blog-details4.html">Modern Cladding Trends</option>
                <option value="blog-details5.html">Innovation in Paving</option>
                <option value="blog-details6.html">It Is a Business Agreement, Not Charity</option>
                <option value="blog-details7.html">How We Turned Challenges into Strength</option>
              </select>
            </label>
            <label class="upload-field">Replacement image <span>*</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required><small>JPG, PNG, WebP, or GIF, maximum 8 MB.</small></label>
            <div class="form-actions"><button class="primary-button" type="submit">Update Blog Image <span>&rarr;</span></button></div>
          </form>
        </div>

        <aside class="managed-list">
          <h3>Active image fixes</h3>
          <?php if (!$overrides['products'] && !$overrides['blogs']): ?><p class="empty-state">Replacement images for existing content will appear here.</p><?php endif; ?>
          <?php foreach ($overrides['products'] as $identifier => $override): ?>
          <article class="managed-item">
            <img src="../<?= e((string) ($override['image'] ?? 'img/logo2.jpg')) ?>" alt="">
            <div><strong><?= e((string) $identifier) ?></strong><span>Existing product SKU</span></div>
            <form method="post" class="delete-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_image_override"><input type="hidden" name="override_type" value="product"><input type="hidden" name="identifier" value="<?= e((string) $identifier) ?>"><button class="icon-button danger" type="submit">Remove</button></form>
          </article>
          <?php endforeach; ?>
          <?php foreach ($overrides['blogs'] as $identifier => $override): ?>
          <article class="managed-item">
            <img src="../<?= e((string) ($override['image'] ?? 'img/logo2.jpg')) ?>" alt="">
            <div><strong><?= e((string) $identifier) ?></strong><span>Existing blog page</span></div>
            <form method="post" class="delete-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_image_override"><input type="hidden" name="override_type" value="blog"><input type="hidden" name="identifier" value="<?= e((string) $identifier) ?>"><button class="icon-button danger" type="submit">Remove</button></form>
          </article>
          <?php endforeach; ?>
        </aside>
      </div>
    </section>
  </main>
  <script type="application/json" id="product-data"><?= json_encode($products, $jsonFlags) ?></script>
  <script type="application/json" id="project-data"><?= json_encode($projects, $jsonFlags) ?></script>
  <script type="application/json" id="blog-data"><?= json_encode($posts, $jsonFlags) ?></script>
  <script src="assets/admin.js?v=3"></script>
<?php endif; ?>
</body>
</html>
