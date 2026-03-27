document.addEventListener("DOMContentLoaded", () => {
    const baseUrl = document.body.dataset.baseUrl || "";
    const alertPlaceholder = document.querySelector("#alertPlaceholder");
    
    // Function to create and show modal
    window.showModal = (title, message) => {
        // Create modal HTML
        const modalHtml = `
            <div class="modal fade" id="customModal" tabindex="-1" aria-labelledby="customModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="customModalLabel">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>${message}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if present
        const existingModal = document.getElementById('customModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('customModal'));
        modal.show();
        
        // Remove modal from DOM after it's hidden
        document.getElementById('customModal').addEventListener('hidden.bs.modal', function () {
            this.remove();
        });
    };
    
    document.querySelectorAll("form.needs-validation").forEach(form => {
        form.addEventListener("submit", event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add("was-validated");
        });

        // Add custom validation for phone numbers
        const phoneInputs = form.querySelectorAll('input[pattern="[0-9]{10}"]');
        phoneInputs.forEach(input => {
            input.addEventListener('input', function() {
                // Remove any non-digit characters
                this.value = this.value.replace(/\D/g, '');
                // Limit to 10 digits
                if (this.value.length > 10) {
                    this.value = this.value.slice(0, 10);
                }
            });
        });

        // Add custom validation for email fields
        const emailInputs = form.querySelectorAll('input[pattern*="@gmail.com"]');
        emailInputs.forEach(input => {
            input.addEventListener('blur', function() {
                const email = this.value.toLowerCase();
                if (email && !email.endsWith('@gmail.com')) {
                    this.setCustomValidity('Email must end with @gmail.com');
                } else {
                    this.setCustomValidity('');
                }
            });
        });

        // Add character counter for description fields
        const textareas = form.querySelectorAll('textarea[minlength="20"]');
        textareas.forEach(textarea => {
            // Add character counter display
            const counter = document.createElement('small');
            counter.className = 'text-muted';
            counter.textContent = '0/20 characters minimum';
            textarea.parentNode.appendChild(counter);

            textarea.addEventListener('input', function() {
                const length = this.value.length;
                counter.textContent = `${length}/20 characters minimum`;
                if (length >= 20) {
                    counter.classList.remove('text-danger');
                    counter.classList.add('text-success');
                } else {
                    counter.classList.remove('text-success');
                    counter.classList.add('text-danger');
                }
            });
        });
    });

    const bindSubmission = (formId, type) => {
        const form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            if (!form.checkValidity()) {
                event.stopPropagation();
                return;
            }
            const formData = new FormData(form);
            formData.append("item_type", type);
            try {
                const response = await fetch(`${baseUrl}/api/submit-item.php`, {
                    method: "POST",
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    if (data.auto_approved) {
                        // Show special modal for auto-approved lost items
                        showModal("Lost Item Submitted", data.message);
                    } else {
                        // Show modal for found items pending approval
                        showModal("Found Item Submitted", "Your found item submission has been received and is pending admin approval. Kindly return the found item to the admin's office to proceed further.");
                    }
                    form.reset();
                    form.classList.remove("was-validated");
                } else {
                    alert(data.message);
                }
            } catch (error) {
                alert("Submission failed. Try again later.");
            }
        });
    };

    bindSubmission("lostForm", "lost");
    bindSubmission("foundForm", "found");

    document.querySelectorAll("[data-demo-alert]").forEach(btn => {
        btn.addEventListener("click", () => {
            if (alertPlaceholder) {
                alertPlaceholder.innerHTML = `
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        Demo notification sent. Configure email/SMS hooks in production.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
            }
        });
    });
});

