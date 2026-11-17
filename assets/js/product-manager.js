(function () {
  'use strict';

  const NEW_PRODUCT_DURATION_MS = 30 * 24 * 60 * 60 * 1000;
  const MAX_TIMER_DELAY = 2147483647;

  function getNewProductExpiry(product) {
    const storedExpiry = Date.parse(product.newUntil || '');
    if (!Number.isNaN(storedExpiry)) return storedExpiry;
    const createdAt = Date.parse(product.createdAt || '');
    return Number.isNaN(createdAt) ? 0 : createdAt + NEW_PRODUCT_DURATION_MS;
  }

  function isProductNew(product) {
    return Boolean(product.isNew) && getNewProductExpiry(product) > Date.now();
  }

  function removeBadgeAtExpiry(badge, expiry) {
    const remaining = expiry - Date.now();
    if (remaining <= 0) {
      badge.remove();
      return;
    }
    window.setTimeout(() => removeBadgeAtExpiry(badge, expiry), Math.min(remaining + 100, MAX_TIMER_DELAY));
  }

  function addHoverImage(card, imageWrap, primaryImage, secondarySource, altText) {
    if (!imageWrap || !primaryImage) return;
    primaryImage.classList.add('product-card-primary');
    if (!secondarySource || imageWrap.querySelector('.product-card-secondary')) return;

    const secondaryImage = document.createElement('img');
    secondaryImage.className = 'product-card-secondary';
    secondaryImage.src = secondarySource;
    secondaryImage.alt = `${altText} alternate view`;
    secondaryImage.loading = 'lazy';
    secondaryImage.decoding = 'async';
    imageWrap.appendChild(secondaryImage);
    card.classList.add('has-secondary-image');
  }

  function enhanceExistingProductCards() {
    document.querySelectorAll('#Products .construction-card').forEach(card => {
      const imageWrap = card.querySelector('.card-image');
      const primaryImage = imageWrap?.querySelector('img:not(.product-card-secondary)');
      const gallery = String(card.dataset.images || '')
        .split(',')
        .map(source => source.trim())
        .filter(Boolean);
      const currentSource = primaryImage?.getAttribute('src') || '';
      const secondarySource = gallery.find(source => source !== currentSource);
      const title = card.querySelector('.card-title');
      const badge = card.querySelector('.card-badge');
      const content = card.querySelector('.card-content');

      addHoverImage(card, imageWrap, primaryImage, secondarySource, title?.textContent.trim() || 'Product');

      if (content && title && !content.querySelector('.card-subtitle')) {
        const category = document.createElement('p');
        category.className = 'card-subtitle';
        category.textContent = badge?.textContent.trim() || 'TEMACO Product';
        title.insertAdjacentElement('afterend', category);
      }
    });
  }

  function buildProductCard(product) {
    const card = document.createElement('a');
    card.className = 'construction-card managed-home-product';
    card.href = `Catalogue1.html?product=${encodeURIComponent(product.id)}`;
    card.setAttribute('aria-label', `View ${product.name} in the product catalogue`);

    const category = document.createElement('div');
    category.className = 'card-badge';
    category.textContent = product.category || 'Product';
    card.appendChild(category);

    if (isProductNew(product)) {
      const newBadge = document.createElement('span');
      newBadge.className = 'managed-product-new-badge';
      newBadge.textContent = 'New Product';
      card.appendChild(newBadge);
      removeBadgeAtExpiry(newBadge, getNewProductExpiry(product));
    }

    const imageWrap = document.createElement('div');
    imageWrap.className = 'card-image';
    const image = document.createElement('img');
    image.src = product.gallery[0].url;
    image.alt = product.name;
    image.loading = 'lazy';
    image.decoding = 'async';
    const overlay = document.createElement('div');
    overlay.className = 'image-overlay';
    const icon = document.createElement('i');
    icon.className = 'fas fa-eye overlay-icon';
    overlay.appendChild(icon);
    imageWrap.append(image, overlay);
    addHoverImage(card, imageWrap, image, product.gallery[1]?.url, product.name);

    const content = document.createElement('div');
    content.className = 'card-content';
    const sku = document.createElement('p');
    sku.className = 'card-subtitle';
    sku.textContent = product.category || product.sku || 'TEMACO Product';
    const title = document.createElement('h3');
    title.className = 'card-title';
    title.textContent = product.name;
    const description = document.createElement('p');
    description.className = 'card-description';
    description.textContent = product.description || 'Explore this TEMACO product and request a quote for current availability.';
    content.append(sku, title, description);
    card.append(imageWrap, content);
    return card;
  }

  async function loadHomepageProducts() {
    try {
      const response = await fetch('data/products.json', { cache: 'no-store' });
      if (!response.ok) return [];
      const records = await response.json();
      return Array.isArray(records) ? records : [];
    } catch (_) {
      return [];
    }
  }

  async function loadProductOverrides() {
    try {
      const response = await fetch('data/content-overrides.json', { cache: 'no-store' });
      if (!response.ok) return {};
      const overrides = await response.json();
      return overrides && typeof overrides.products === 'object' ? overrides.products : {};
    } catch (_) {
      return {};
    }
  }

  function applyExistingProductImages(overrides) {
    document.querySelectorAll('#Products .construction-card[data-sku]').forEach(card => {
      const replacement = overrides[String(card.dataset.sku || '').toUpperCase()]?.image;
      const image = card.querySelector('.card-image img');
      if (image && replacement) image.src = replacement;
    });
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const grid = document.querySelector('#Products .products-card-grid');
    if (!grid) return;
    enhanceExistingProductCards();
    const [homepageProducts, overrides] = await Promise.all([loadHomepageProducts(), loadProductOverrides()]);
    applyExistingProductImages(overrides);
    const products = homepageProducts
      .filter(product => product && product.featureHome === true && product.name && Array.isArray(product.gallery) && product.gallery.length)
      .map(product => {
        const replacement = overrides[String(product.sku || '').toUpperCase()]?.image;
        if (!replacement) return product;
        return { ...product, gallery: [{ url: replacement, label: `${product.name} replacement image` }, ...product.gallery.slice(1)] };
      })
      .sort((a, b) => String(b.createdAt || '').localeCompare(String(a.createdAt || '')));
    if (!products.length) return;
    const fragment = document.createDocumentFragment();
    products.forEach(product => fragment.appendChild(buildProductCard(product)));
    grid.prepend(fragment);
  });
})();
