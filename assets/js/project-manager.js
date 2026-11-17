(function () {
  'use strict';

  function galleryUrls(project) {
    return Array.isArray(project.gallery)
      ? project.gallery.map(image => typeof image === 'string' ? image : image && image.url).filter(Boolean)
      : [];
  }

  function makeMeta(icon, text) {
    const item = document.createElement('div');
    item.className = 'project-meta-item';
    const symbol = document.createElement('i');
    symbol.className = icon;
    const label = document.createElement('span');
    label.textContent = text;
    item.append(symbol, label);
    return item;
  }

  function makeProjectCard(project) {
    const images = galleryUrls(project);
    if (!images.length || !project.slug) return null;

    const card = document.createElement('div');
    card.className = 'project-card swiper-slide panel-project-card';
    card.dataset.subtitle = project.status || 'TEMACO Project';
    card.dataset.image = images[0];
    card.dataset.tech = project.scope || '';
    card.dataset.images = images.join(',');

    const imageWrap = document.createElement('div');
    imageWrap.className = 'project-image';
    const image = document.createElement('img');
    image.src = images[0];
    image.alt = project.name || 'TEMACO project';
    image.loading = 'lazy';
    image.decoding = 'async';
    const category = document.createElement('div');
    category.className = 'project-category';
    category.textContent = project.category || 'TEMACO Project';
    imageWrap.append(image, category);

    const content = document.createElement('div');
    content.className = 'project-content';
    const title = document.createElement('h3');
    title.className = 'project-title';
    title.textContent = project.name || 'TEMACO Project';
    const description = document.createElement('p');
    description.className = 'project-description';
    description.textContent = project.description || '';

    const footer = document.createElement('div');
    footer.className = 'project-footer';
    const meta = document.createElement('div');
    meta.className = 'project-meta';
    meta.append(
      makeMeta('fas fa-tools', project.scope || 'Project delivery'),
      makeMeta('fas fa-map-marker-alt', project.location || 'Rwanda'),
      makeMeta('fas fa-hard-hat', project.status || 'Ongoing')
    );
    const link = document.createElement('a');
    link.className = 'view-details-btn';
    link.href = 'project-details.html?project=' + encodeURIComponent(project.slug);
    link.append(document.createTextNode('View Details '));
    const arrow = document.createElement('i');
    arrow.className = 'fas fa-arrow-right';
    link.appendChild(arrow);
    footer.append(meta, link);
    content.append(title, description, footer);
    card.append(imageWrap, content);
    return card;
  }

  window.temacoProjectsReady = fetch('data/projects.json', { cache: 'no-store' })
    .then(response => response.ok ? response.json() : [])
    .then(records => {
      const wrapper = document.querySelector('.projects-slider .swiper-wrapper');
      if (!wrapper || !Array.isArray(records)) return [];
      const fragment = document.createDocumentFragment();
      const published = records.filter(project => project && project.featureHome !== false);
      published.forEach(project => {
        const card = makeProjectCard(project);
        if (card) fragment.appendChild(card);
      });
      wrapper.prepend(fragment);
      return published;
    })
    .catch(() => []);
})();
