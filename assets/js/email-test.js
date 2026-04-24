document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const resultDiv = document.getElementById('result');
            
            if (resultDiv) {
                resultDiv.innerHTML = 'Sending email...';
                resultDiv.className = 'result';
            }
            
            fetch('send_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (resultDiv) {
                    if (data.status === 'success') {
                        resultDiv.className = 'result success';
                        resultDiv.innerHTML = '✅ ' + data.message;
                    } else {
                        resultDiv.className = 'result error';
                        resultDiv.innerHTML = '❌ ' + data.message;
                    }
                }
            })
            .catch(error => {
                if (resultDiv) {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = '❌ Network error: ' + error.message;
                }
            });
        });
    }
});