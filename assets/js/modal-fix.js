// Fix for modal and swiper errors
document.addEventListener('DOMContentLoaded', function() {
    // Wait for all scripts to load
    setTimeout(() => {
        // Fix duplicate modal handlers
        const existingHandlers = document.querySelectorAll('.construction-card .image-overlay');
        existingHandlers.forEach(overlay => {
            // Remove existing event listeners by cloning
            const newOverlay = overlay.cloneNode(true);
            overlay.parentNode.replaceChild(newOverlay, overlay);
        });

        // Add single, safe modal handler
        document.querySelectorAll('.construction-card .image-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                e.stopPropagation();
                const card = this.closest('.construction-card');
                const images = (card.getAttribute('data-images') || '').split(',').map(s => s.trim()).filter(Boolean);
                if (!images.length) return;
                
                const title = card.querySelector('.card-title')?.textContent || '';
                const desc = card.querySelector('.card-description')?.textContent || '';
                
                // Find modal
                const modal = document.getElementById('product-modal') || document.getElementById('project-modal');
                if (!modal) return;
                
                // Populate modal safely
                const titleEl = modal.querySelector('#product-modal-title, .product-modal-title');
                const descEl = modal.querySelector('#product-modal-description, .product-modal-description');
                const wrapper = modal.querySelector('#product-modal-swiper-wrapper, .swiper-wrapper');
                
                if (titleEl) titleEl.textContent = title;
                if (descEl) descEl.textContent = desc;
                if (wrapper) {
                    wrapper.innerHTML = images.map(img => 
                        `<div class="swiper-slide"><img src="${img}" alt="Product Image"></div>`
                    ).join('');
                }
                
                // Show modal
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                // Initialize swiper safely
                setTimeout(() => {
                    const swiperEl = modal.querySelector('.swiper, .product-modal-swiper');
                    if (swiperEl && typeof Swiper !== 'undefined') {
                        try {
                            new Swiper(swiperEl, {
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
                            });
                        } catch (error) {
                            console.log('Swiper initialization skipped:', error.message);
                        }
                    }
                }, 150);
            });
        });

        // Fix modal close handlers
        document.querySelectorAll('.product-modal-close, .modal-close').forEach(closeBtn => {
            closeBtn.addEventListener('click', function() {
                const modal = this.closest('.product-modal-overlay, .modal-overlay') || 
                             document.getElementById('product-modal') || 
                             document.getElementById('project-modal');
                if (modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

    }, 500); // Wait for all scripts to load
});
