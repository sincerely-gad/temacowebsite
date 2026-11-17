(function () {
  'use strict';

  const legacyHighlights = [
    {
      title: 'How We Turned Challenges into Strength',
      excerpt: 'A 10-day journey of research and innovation that transformed the way TEMACO produces paving blocks.',
      publishedAt: '2025-09-10',
      author: 'TEMACO Team',
      image: 'img/blog/blog 7/1.jpg',
      href: 'blog-details7.html',
      legacy: true
    },
    {
      title: 'Award-Winning Projects',
      excerpt: 'The quality, innovation, and teamwork behind TEMACO’s recognized projects.',
      publishedAt: '2024-06-01',
      author: 'TEMACO Team',
      image: 'img/blog/blog2/b.jpg',
      href: 'blog-details2.html',
      legacy: true
    },
    {
      title: 'TEMACO Builders Start-Up Story',
      excerpt: 'How a clear mission, practical skills, and decisive action grew into meaningful community impact.',
      publishedAt: '2024-05-01',
      author: 'TEMACO Team',
      image: 'img/blog/blog3/DSC_9839.jpg',
      href: 'blog-details3.html',
      legacy: true
    }
  ];

  const formatDate = value => {
    const date = new Date(`${value || ''}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value || '';
    return new Intl.DateTimeFormat('en', { year: 'numeric', month: 'long', day: 'numeric' }).format(date);
  };

  const postHref = post => post.href || `blog-post.html?slug=${encodeURIComponent(post.slug || '')}`;

  function buildHomepageCard(post) {
    const article = document.createElement('article');
    article.className = 'home-blog-card';
    const link = document.createElement('a');
    link.className = 'home-blog-image';
    link.href = postHref(post);
    const image = document.createElement('img');
    image.src = post.image;
    image.alt = post.title;
    image.loading = 'lazy';
    link.appendChild(image);

    const body = document.createElement('div');
    body.className = 'home-blog-body';
    const meta = document.createElement('p');
    meta.className = 'home-blog-meta';
    meta.textContent = `${formatDate(post.publishedAt)} · ${post.author || 'TEMACO Team'}`;
    const title = document.createElement('h3');
    const titleLink = document.createElement('a');
    titleLink.href = postHref(post);
    titleLink.textContent = post.title;
    title.appendChild(titleLink);
    const excerpt = document.createElement('p');
    excerpt.className = 'home-blog-excerpt';
    excerpt.textContent = post.excerpt;
    const readMore = document.createElement('a');
    readMore.className = 'home-blog-link';
    readMore.href = postHref(post);
    readMore.textContent = 'Read Story →';
    body.append(meta, title, excerpt, readMore);
    article.append(link, body);
    return article;
  }

  function buildBlogCard(post) {
    const card = document.createElement('div');
    card.className = 'blog-card managed-blog-card';
    const image = document.createElement('img');
    image.src = post.image;
    image.alt = post.title;
    image.loading = 'lazy';
    const content = document.createElement('div');
    content.className = 'blog-card-content';
    const title = document.createElement('div');
    title.className = 'blog-card-title';
    title.textContent = post.title;
    const date = document.createElement('div');
    date.className = 'blog-card-date';
    date.textContent = formatDate(post.publishedAt);
    const excerpt = document.createElement('div');
    excerpt.className = 'blog-card-excerpt';
    excerpt.textContent = post.excerpt;
    const link = document.createElement('a');
    link.className = 'blog-card-readmore';
    link.href = postHref(post);
    link.textContent = 'Read More';
    content.append(title, date, excerpt, link);
    card.append(image, content);
    return card;
  }

  async function loadManagedPosts() {
    try {
      const response = await fetch('data/blog-posts.json', { cache: 'no-store' });
      if (!response.ok) return [];
      const posts = await response.json();
      return Array.isArray(posts) ? posts : [];
    } catch (_) {
      return [];
    }
  }

  async function loadImageOverrides() {
    try {
      const response = await fetch('data/content-overrides.json', { cache: 'no-store' });
      if (!response.ok) return {};
      const overrides = await response.json();
      return overrides && typeof overrides.blogs === 'object' ? overrides.blogs : {};
    } catch (_) {
      return {};
    }
  }

  const pageName = value => {
    try {
      return new URL(value, window.location.href).pathname.split('/').pop().toLowerCase();
    } catch (_) {
      return String(value || '').split('?')[0].split('/').pop().toLowerCase();
    }
  };

  function applyStaticBlogImages(overrides) {
    document.querySelectorAll('.blog-card').forEach(card => {
      const link = card.querySelector('a[href*="blog-details"]');
      const image = card.querySelector('img');
      const replacement = overrides[pageName(link?.getAttribute('href'))]?.image;
      if (image && replacement) image.src = replacement;
    });

    const currentPage = pageName(window.location.pathname);
    const detailReplacement = overrides[currentPage]?.image;
    if (!detailReplacement) return;
    const featureImage = document.querySelector('.blog-feature-card img, .blog-main-content > img:first-of-type, main.blog-main-content img:first-of-type');
    if (featureImage) featureImage.src = detailReplacement;
  }

  document.addEventListener('DOMContentLoaded', async () => {
    const [managedPosts, blogOverrides] = await Promise.all([loadManagedPosts(), loadImageOverrides()]);
    const managed = managedPosts.sort((a, b) => String(b.publishedAt || '').localeCompare(String(a.publishedAt || '')));
    const legacyWithOverrides = legacyHighlights.map(post => ({
      ...post,
      image: blogOverrides[pageName(post.href)]?.image || post.image
    }));
    applyStaticBlogImages(blogOverrides);
    const homepageGrid = document.getElementById('homepage-blog-grid');
    if (homepageGrid) {
      const featured = managed.filter(post => post.featureHome);
      const remaining = managed.filter(post => !post.featureHome);
      [...featured, ...remaining, ...legacyWithOverrides].slice(0, 3).forEach(post => homepageGrid.appendChild(buildHomepageCard(post)));
    }

    const blogGrid = document.querySelector('body.blog-page .blog-grid');
    if (blogGrid && !homepageGrid) {
      [...managed].reverse().forEach(post => blogGrid.prepend(buildBlogCard(post)));
    }
  });
})();
