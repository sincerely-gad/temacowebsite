# TEMACO content panel

Open `/admin/` on the deployed website. The first visit asks the site owner to create a password with at least 12 characters. After setup, that screen becomes the normal sign-in page.

## Hosting requirements

- PHP 7.4 or newer
- File uploads enabled
- Write permission for `admin/`, `data/`, and `uploads/`
- An 8 MB or larger PHP upload limit

The password is stored as a one-way hash in `admin/.credentials.php`. That generated file is intentionally excluded from Git. Public product, project, and blog metadata is stored in `data/`; uploaded media is stored in `uploads/`.

The **Image fixes** section can replace the displayed image for an existing hard-coded catalogue product or blog post without migrating that content into the panel. Products are matched by their exact SKU. Existing blog posts are selected by page name. These replacements are stored in `data/content-overrides.json` and `uploads/overrides/`.

Back up `data/`, `uploads/`, and `admin/.credentials.php` when moving the website or taking a full content backup.
