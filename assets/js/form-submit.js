document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form.tw-form');
    if (!forms.length) {
        return;
    }

    forms.forEach(function(form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault(); // Prevent the default page reload

            // --- THIS IS THE FIX ---
            // First, find the main container that holds both the form and the status div.
            const container = form.closest('.tw-form-container');
            if (!container) {
                console.error('TW Forms: Could not find parent .tw-form-container.');
                return;
            }
            
            const button = form.querySelector('button[name="submit_tw_form"]');
            // Now, find the status div within the main container.
            const statusDiv = container.querySelector('.form-status-message');
            // --- END FIX ---
            
            const originalButtonText = button.textContent;

            button.disabled = true;
            button.textContent = 'Submitting...';
            if (statusDiv) statusDiv.innerHTML = ''; // Clear previous messages

            const formData = new FormData(form);
            formData.append('action', 'tw_forms_submit');

            const sendAjaxRequest = (formData) => {
                fetch(twForms.ajaxurl, {
                    method: 'POST',
                    body: formData,
                })
                .then(response => response.json())
                .then(data => {
                    if (statusDiv) {
                        if (data.success) {
                            statusDiv.innerHTML = data.data.message;
                            form.reset();
                        } else {
                            statusDiv.innerHTML = data.data.message;
                        }
                    }
                })
                .catch(error => {
                    if (statusDiv) statusDiv.innerHTML = '<p style="color: red;">A network error occurred. Please try again.</p>';
                    console.error('Form Submission Error:', error);
                })
                .finally(() => {
                    button.disabled = false;
                    button.textContent = originalButtonText;
                });
            };

            if (twForms.siteKey && typeof grecaptcha !== 'undefined') {
                button.textContent = 'Verifying...';
                grecaptcha.ready(function() {
                    grecaptcha.execute(twForms.siteKey, { action: 'form_submit' }).then(function(token) {
                        formData.append('recaptcha_token', token);

                        let tokenInput = form.querySelector('input[name="recaptcha_token"]');
                        if (!tokenInput) {
                            tokenInput = document.createElement('input');
                            tokenInput.type = 'hidden';
                            tokenInput.name = 'recaptcha_token';
                            form.appendChild(tokenInput);
                        }
                        tokenInput.value = token;

                        sendAjaxRequest(formData);
                    }).catch(function(error) {
                        if (statusDiv) statusDiv.innerHTML = '<p style="color: red;">Could not get spam protection token. Please try again.</p>';
                        button.disabled = false;
                        button.textContent = originalButtonText;
                    });
                });
            } else {
                sendAjaxRequest(formData);
            }
        });
    });
});
