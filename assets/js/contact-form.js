// Contact form submission handler
document.addEventListener('DOMContentLoaded', function() {
  const contactForm = document.getElementById('contactForm');
  if (contactForm) {
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