(function () {
  'use strict';

  const parseData = id => {
    const node = document.getElementById(id);
    if (!node) return [];
    try { return JSON.parse(node.textContent || '[]'); } catch (_) { return []; }
  };

  const products = parseData('product-data');
  const projects = parseData('project-data');
  const posts = parseData('blog-data');
  const productForm = document.getElementById('product-form');
  const projectForm = document.getElementById('project-form');
  const blogForm = document.getElementById('blog-form');
  const specRows = document.getElementById('spec-rows');
  const NEW_PRODUCT_DURATION_MS = 30 * 24 * 60 * 60 * 1000;

  function isProductNew(product) {
    if (!product?.isNew) return false;
    const storedExpiry = Date.parse(product.newUntil || '');
    const createdAt = Date.parse(product.createdAt || '');
    const expiry = Number.isNaN(storedExpiry)
      ? (Number.isNaN(createdAt) ? 0 : createdAt + NEW_PRODUCT_DURATION_MS)
      : storedExpiry;
    return expiry > Date.now();
  }

  function addSpec(name = '', value = '') {
    if (!specRows) return;
    const row = document.createElement('div');
    row.className = 'spec-row';
    row.innerHTML = '<input name="spec_name[]" placeholder="Specification, e.g. Thickness"><input name="spec_value[]" placeholder="Value, e.g. 60 mm"><button class="remove-spec" type="button" aria-label="Remove specification">×</button>';
    row.querySelector('[name="spec_name[]"]').value = name;
    row.querySelector('[name="spec_value[]"]').value = value;
    row.querySelector('.remove-spec').addEventListener('click', () => row.remove());
    specRows.appendChild(row);
  }

  document.getElementById('add-spec')?.addEventListener('click', () => addSpec());
  if (specRows && !specRows.children.length) addSpec();

  function setValue(form, name, value) {
    const field = form?.elements.namedItem(name);
    if (field) field.value = value ?? '';
  }

  document.querySelectorAll('.edit-product').forEach(button => button.addEventListener('click', () => {
    const product = products.find(item => Number(item.id) === Number(button.dataset.id));
    if (!product || !productForm) return;
    productForm.reset();
    setValue(productForm, 'record_id', product.id);
    setValue(productForm, 'name', product.name);
    setValue(productForm, 'category', product.category);
    setValue(productForm, 'sku', product.sku);
    setValue(productForm, 'price', product.price);
    setValue(productForm, 'price_note', product.priceNote);
    setValue(productForm, 'description', product.description);
    productForm.elements.namedItem('feature_home').checked = Boolean(product.featureHome);
    productForm.elements.namedItem('is_new').checked = isProductNew(product);
    specRows.innerHTML = '';
    (product.specs || []).forEach(spec => addSpec(spec[0], spec[1]));
    if (!(product.specs || []).length) addSpec();
    productForm.querySelector('.primary-button').firstChild.textContent = 'Update Product ';
    productForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }));

  document.querySelectorAll('.edit-blog').forEach(button => button.addEventListener('click', () => {
    const post = posts.find(item => Number(item.id) === Number(button.dataset.id));
    if (!post || !blogForm) return;
    blogForm.reset();
    setValue(blogForm, 'record_id', post.id);
    setValue(blogForm, 'title', post.title);
    setValue(blogForm, 'slug', post.slug);
    setValue(blogForm, 'author', post.author);
    setValue(blogForm, 'published_at', post.publishedAt);
    setValue(blogForm, 'excerpt', post.excerpt);
    setValue(blogForm, 'content', post.content);
    blogForm.elements.namedItem('feature_home').checked = Boolean(post.featureHome);
    blogForm.querySelector('.primary-button').firstChild.textContent = 'Update Blog Post ';
    blogForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }));

  document.querySelectorAll('.edit-project').forEach(button => button.addEventListener('click', () => {
    const project = projects.find(item => Number(item.id) === Number(button.dataset.id));
    if (!project || !projectForm) return;
    projectForm.reset();
    setValue(projectForm, 'record_id', project.id);
    setValue(projectForm, 'name', project.name);
    setValue(projectForm, 'slug', project.slug);
    setValue(projectForm, 'category', project.category);
    setValue(projectForm, 'status', project.status);
    setValue(projectForm, 'location', project.location);
    setValue(projectForm, 'scope', project.scope);
    setValue(projectForm, 'delivery', project.delivery);
    setValue(projectForm, 'description', project.description);
    projectForm.elements.namedItem('feature_home').checked = Boolean(project.featureHome);
    projectForm.querySelector('.primary-button').firstChild.textContent = 'Update Project ';
    projectForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }));

  document.querySelectorAll('.reset-editor').forEach(button => button.addEventListener('click', () => {
    const form = button.closest('form');
    form?.reset();
    setValue(form, 'record_id', '');
    if (form === productForm && specRows) { specRows.innerHTML = ''; addSpec(); }
    const submit = form?.querySelector('.primary-button');
    if (submit) {
      submit.firstChild.textContent = form === productForm
        ? 'Publish Product '
        : form === projectForm
          ? 'Publish Project '
          : 'Publish Blog Post ';
    }
  }));

  document.querySelectorAll('.delete-form').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm('Remove this item from the public website? Uploaded files will be retained.')) event.preventDefault();
  }));
})();
