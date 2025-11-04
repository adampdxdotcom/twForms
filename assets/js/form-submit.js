document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form.tw-form');
    if (!forms.length) {
        return;
    }

    forms.forEach(function(form) {
        // We listen for the 'submit' event on the form itself, not the button click.
        // This is more robust and respects HTML5 validation.
        form.addEventListener('submit', function (e) {
            e.preventDefault(); // Always prevent the default page reload

            const button = form.querySelector('button[name="submit_tw_form"]');
            const statusDiv = form.querySelector('.form-status-message');
            const originalButtonText = button.textContent;

            button.disabled = true;
            button.textContent = 'Submitting...';
            statusDiv.innerHTML = '';

            // This function sends the actual AJAX request.
            const sendAjaxRequest = (formData) => {
                fetch(twForms.ajaxurl, {
                    method: 'POST',
                    body: formData,
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        statusDiv.innerHTML = data.data.message;
                        form.reset();
                    } else {
                        statusDiv.innerHTML = data.data.message;
                    }
                })
                .catch(error => {
                    statusDiv.innerHTML = '<p style="color: red;">A network error occurred. Please try again.</p>';
                    console.error('Form Submission Error:', error);
                })
                .finally(() => {
                    button.disabled = false;
                    button.textContent = originalButtonText;
                });
            };

            const formData = new FormData(form);
            formData.append('action', 'tw_forms_submit');

            // --- NEW: reCAPTCHA Integration ---
            // Check if reCAPTCHA is enabled for this site (twForms.siteKey will exist).
            if (twForms.siteKey && typeof grecaptcha !== 'undefined') {
                button.textContent = 'Verifying...';
                grecaptcha.ready(function() {
                    grecaptcha.execute(twForms.siteKey, { action: 'form_submit' }).then(function(token) {
                        formData.append('recaptcha_token', token);
                        sendAjaxRequest(formData); // Send request AFTER getting token
                    }).catch(function(error) {
                        statusDiv.innerHTML = '<p style="color: red;">Could not get spam protection token. Please try again.</p>';
                        button.disabled = false;
                        button.textContent = originalButtonText;
                    });
                });
            } else {
                // If reCAPTCHA is not enabled, send the request immediately.
                sendAjaxRequest(formData);
            }
        });
    });
});
