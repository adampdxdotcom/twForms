document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form.tw-form');
    if (!forms.length) {
        return;
    }

    forms.forEach(function(form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault(); // Prevent the default page reload

            const button = form.querySelector('button[name="submit_tw_form"]');
            const statusDiv = form.querySelector('.form-status-message');
            const originalButtonText = button.textContent;

            // Provide immediate feedback to the user
            button.disabled = true;
            button.textContent = 'Submitting...';
            statusDiv.innerHTML = ''; // Clear previous messages

            // Collect all form data automatically
            const formData = new FormData(form);
            formData.append('action', 'tw_forms_submit'); // This is crucial for our AJAX endpoint

            // Send the data to the server
            fetch(twForms.ajaxurl, {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // On success, show the message and reset the form
                    statusDiv.innerHTML = data.data.message;
                    form.reset();
                } else {
                    // On failure, build and display the error list
                    statusDiv.innerHTML = data.data.message;
                }
            })
            .catch(error => {
                // Handle network errors
                statusDiv.innerHTML = '<p style="color: red;">A network error occurred. Please try again.</p>';
                console.error('Form Submission Error:', error);
            })
            .finally(() => {
                // Always re-enable the button
                button.disabled = false;
                button.textContent = originalButtonText;
            });
        });
    });
});
