

  // Project modal logic
  let projectModal = document.getElementById('project-modal');
  let projectCloseBtn = projectModal.querySelector('.product-modal-close');
  let projectSwiper = null;

  function initProjectSwiper() {
    if (projectSwiper) return projectSwiper;
    
    projectSwiper = new Swiper('.product-modal-swiper', {
      slidesPerView: 1,
      spaceBetween: 0,
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      on: {
        init: function() {
          document.querySelector('.product-modal-swiper').style.opacity = '1';
        }
      }
    });
    
    return projectSwiper;
  }

  function populateProjectModal(title, desc, images) {
    document.getElementById('project-modal-title').textContent = title;
    document.getElementById('project-modal-description').textContent = desc;
    const wrapper = document.getElementById('project-modal-swiper-wrapper');
    
    // Filter out problematic Hoima image and any empty images
    images = images.filter(img => {
      img = img.trim();
      return img && !img.includes('hoima/7.png');
    });
    
    wrapper.innerHTML = images.map(img => 
      `<div class="swiper-slide"><img src="${img}" alt="Project Image" style="width:100%;height:100%;object-fit:contain;"></div>`
    ).join('');
    
    // Initialize or update swiper
    const swiper = initProjectSwiper();
    if (swiper) {
      swiper.update();
      swiper.slideTo(0);
    }
  }

  document.querySelectorAll('.project-card .view-details-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const card = this.closest('.project-card');
      if (!card) return;
      
      // Get and filter images
      let images = (card.getAttribute('data-images') || '').split(',').map(s => s.trim()).filter(Boolean);
      
      // Remove problematic Hoima image
      images = images.filter(img => !img.includes('hoima/7.png'));
      
      if (!images.length) return;
      
      const title = card.querySelector('.project-title')?.textContent || '';
      const desc = card.querySelector('.project-description')?.textContent || '';
      
      // Update modal content
      document.getElementById('project-modal-title').textContent = title;
      document.getElementById('project-modal-description').textContent = desc;
      
      // Update swiper slides
      const wrapper = document.getElementById('project-modal-swiper-wrapper');
      wrapper.innerHTML = images.map(img => 
        `<div class="swiper-slide"><img src="${img}" alt="Project Image"></div>`
      ).join('');
      
      // Show modal and update swiper
      projectModal.classList.add('active');
      document.body.style.overflow = 'hidden';
      projectModalSwiper.update();
      projectModalSwiper.slideTo(0);
    });
  });
  projectCloseBtn.addEventListener('click', function() {
    projectModal.classList.remove('active');
    document.body.style.overflow = 'auto';
  });
  projectModal.addEventListener('click', function(e) {
    if (e.target === projectModal) {
      projectModal.classList.remove('active');
      document.body.style.overflow = 'auto';
    }
  });

  // Only open lightbox when clicking an image inside the Swiper in the project modal
  document.getElementById('project-modal-swiper-wrapper').addEventListener('click', function(e) {
    if (e.target.tagName === 'IMG') {
      const imgEls = Array.from(this.querySelectorAll('img'));
      const imgArr = imgEls.map(img => img.src);
      const idx = imgEls.indexOf(e.target);
      if (idx !== -1 && window.openLightboxForProject) {
        window.openLightboxForProject(imgArr, idx);
      }
    }
  });

  // Clicking a thumbnail navigates Swiper
  // Removed event listener for project-modal-images-grid
});