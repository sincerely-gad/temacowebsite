// Professional Phone Number Formatter
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('phone');
    
    if (phoneInput) {
        // Format phone number as user types
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
            
            // Rwanda phone number formatting
            if (value.startsWith('250')) {
                // International format: +250 7XX XXX XXX
                if (value.length <= 3) {
                    value = '+' + value;
                } else if (value.length <= 6) {
                    value = '+' + value.slice(0, 3) + ' ' + value.slice(3);
                } else if (value.length <= 9) {
                    value = '+' + value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6);
                } else if (value.length <= 12) {
                    value = '+' + value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6, 9) + ' ' + value.slice(9, 12);
                }
            } else if (value.startsWith('7') || value.startsWith('0')) {
                // Local format: 07XX XXX XXX or 7XX XXX XXX
                if (value.startsWith('0')) {
                    value = value.slice(1); // Remove leading 0
                }
                if (value.length <= 3) {
                    value = '+250 ' + value;
                } else if (value.length <= 6) {
                    value = '+250 ' + value.slice(0, 3) + ' ' + value.slice(3);
                } else if (value.length <= 9) {
                    value = '+250 ' + value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6);
                }
            } else if (value.length > 0) {
                // Other international numbers
                if (value.length <= 3) {
                    value = '+' + value;
                } else {
                    value = '+' + value.slice(0, 3) + ' ' + value.slice(3);
                }
            }
            
            e.target.value = value;
        });

        // Validate phone number on blur
        phoneInput.addEventListener('blur', function(e) {
            const value = e.target.value;
            const isValid = /^\+250\s7\d{2}\s\d{3}\s\d{3}$/.test(value) || 
                           /^\+\d{1,3}\s[\d\s]{6,}$/.test(value);
            
            if (value && !isValid) {
                e.target.style.borderColor = '#dc3545';
                showPhoneError('Please enter a valid phone number (e.g., +250 788 306 074)');
            } else {
                e.target.style.borderColor = '#e1e5e9';
                hidePhoneError();
            }
        });
    }
});

function showPhoneError(message) {
    let errorDiv = document.getElementById('phone-error');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = 'phone-error';
        errorDiv.style.cssText = 'color: #dc3545; font-size: 0.875rem; margin-top: 5px;';
        document.getElementById('phone').parentNode.appendChild(errorDiv);
    }
    errorDiv.textContent = message;
}

function hidePhoneError() {
    const errorDiv = document.getElementById('phone-error');
    if (errorDiv) {
        errorDiv.remove();
    }
}
