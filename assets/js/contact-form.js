// Contact form submission handler
document.addEventListener('DOMContentLoaded', function() {
  const contactForm = document.getElementById('contactForm');
  if (contactForm) {
    const jumpToContact = function() {
      const contactSection = document.getElementById('contact');
      if (!contactSection) return;

      const root = document.documentElement;
      const body = document.body;
      const previousRootBehavior = root.style.getPropertyValue('scroll-behavior');
      const previousRootPriority = root.style.getPropertyPriority('scroll-behavior');
      const previousBodyBehavior = body.style.getPropertyValue('scroll-behavior');
      const previousBodyPriority = body.style.getPropertyPriority('scroll-behavior');

      root.style.setProperty('scroll-behavior', 'auto', 'important');
      body.style.setProperty('scroll-behavior', 'auto', 'important');
      const contactTop = window.scrollY + contactSection.getBoundingClientRect().top - 100;
      window.scrollTo(0, Math.max(contactTop, 0));

      window.setTimeout(() => {
        if (previousRootBehavior) {
          root.style.setProperty('scroll-behavior', previousRootBehavior, previousRootPriority);
        } else {
          root.style.removeProperty('scroll-behavior');
        }
        if (previousBodyBehavior) {
          body.style.setProperty('scroll-behavior', previousBodyBehavior, previousBodyPriority);
        } else {
          body.style.removeProperty('scroll-behavior');
        }
      }, 50);
    };

    if (window.location.hash === '#contact' && !new URLSearchParams(window.location.search).has('quoteProduct')) {
      window.setTimeout(jumpToContact, 350);
    }

    const quoteParams = new URLSearchParams(window.location.search);
    const quotedProduct = quoteParams.get('quoteProduct');
    const quotedSku = quoteParams.get('sku');
    const quotedCategory = quoteParams.get('category');
    const quotedProject = quoteParams.get('project');
    const messageField = contactForm.querySelector('#message');
    const projectField = contactForm.querySelector('#project');

    if (quotedProduct && messageField) {
      const skuText = quotedSku ? ` (SKU: ${quotedSku})` : '';
      const categoryText = quotedCategory ? ` from the ${quotedCategory} collection` : '';
      messageField.value = `I would like to request a quote for ${quotedProduct}${skuText}${categoryText}.\n\nQuantity and project details: `;
      if (projectField) projectField.value = 'other';

      const contactHeading = document.querySelector('#contact .heading h2');
      if (contactHeading) contactHeading.textContent = `Request a Quote for ${quotedProduct}`;

      const quoteNotice = document.createElement('div');
      quoteNotice.className = 'quote-request-notice';
      quoteNotice.setAttribute('role', 'status');
      quoteNotice.textContent = `${quotedProduct} has been added to your quote request. Complete the details below and send it to our team.`;
      contactForm.insertBefore(quoteNotice, contactForm.firstChild);
      const quoteSubmitButton = contactForm.querySelector('button[type="submit"]');
      if (quoteSubmitButton) quoteSubmitButton.innerHTML = 'Send Quote Request <span>→</span>';

      if (window.location.hash === '#contact') {
        window.setTimeout(jumpToContact, 350);
        window.setTimeout(jumpToContact, 1200);
      }
    } else if (quotedProject && messageField) {
      messageField.value = `I would like to discuss a similar project to ${quotedProject}.\n\nProject details: `;
      if (projectField) projectField.value = 'infrastructure';
      if (window.location.hash === '#contact') {
        window.setTimeout(jumpToContact, 350);
        window.setTimeout(jumpToContact, 1200);
      }
    }

    // Add onsubmit handler as a backup
    contactForm.onsubmit = function(e) {
      e.preventDefault();
      return false;
    };
    
    contactForm.addEventListener('submit', function(e) {
    e.preventDefault();
    e.stopPropagation();
    console.log('Message Received Successfully!');
    
    const formData = new FormData(this);
    const messageDiv = document.getElementById('formMessage');
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    
    console.log('Elements found:', {
        messageDiv: messageDiv ? 'YES' : 'NO',
        submitBtn: submitBtn ? 'YES' : 'NO'
    });
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.classList.add('btn-loading');
    submitBtn.innerHTML = 'Sending...';
    console.log('Button state changed to loading');
    
    // Show processing message
    messageDiv.style.display = 'block';
    messageDiv.innerHTML = '<div class="alert alert-info">📤 Sending your message, please wait...</div>';
    console.log('Processing message displayed');
    
    fetch('send_email_simple.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response received:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        
        // Remove loading state
        submitBtn.disabled = false;
        submitBtn.classList.remove('btn-loading');
        submitBtn.innerHTML = originalBtnText;
        console.log('Button restored');
        
        messageDiv.style.display = 'block';
        if (data.status === 'success') {
            console.log('Showing success message');
            messageDiv.innerHTML = `
                <div class="alert alert-success">
                    <strong>✅ Thank You!</strong><br>
                    Thank you for submitting your request. We have received your inquiry and will get back to you soon.
                    <br><br>
                    <small>📧 Your message has been sent to our team at info@temacobuilders.com</small>
                </div>
            `;
            this.reset();
            
            // Auto-hide success message after 15 seconds
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 15000);
            
        } else {
            console.log('Showing error message');
            messageDiv.innerHTML = `
                <div class="alert alert-danger">
                    <strong>Failed to Send Message</strong><br>
                    ${data.message || 'There was an error sending your message.'}
                    <br><br>
                    <small>Please try again or contact us directly at info@temacobuilders.com</small>
                </div>
            `;
        }
        
        // Scroll to message
        messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        console.log('Scrolled to message');
        
    })
    .catch(error => {
        console.error('Fetch error:', error);
        
        // Remove loading state
        submitBtn.disabled = false;
        submitBtn.classList.remove('btn-loading');
        submitBtn.innerHTML = originalBtnText;
        
        messageDiv.style.display = 'block';
        messageDiv.innerHTML = `
            <div class="alert alert-danger">
                <strong>Connection Error</strong><br>
                Unable to send your message due to a network error. Please check your internet connection and try again.
                <br><br>
                <small>Alternatively, you can email us directly at info@temacobuilders.com</small>
            </div>
        `;
        
        // Scroll to message
        messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });
  }
});
