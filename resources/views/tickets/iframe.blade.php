{{-- resources/views/tickets/iframe.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Ticket Form</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .ticket-form {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .form-header h2 {
            margin: 0;
            color: #2c3e50;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .error-message {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 5px;
        }
        
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            transition: background-color 0.2s;
        }
        
        button:hover {
            background-color: #2980b9;
        }
        
        .success-message {
            background-color: #2ecc71;
            color: white;
            text-align: center;
            padding: 20px;
            border-radius: 4px;
            margin-top: 20px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .file-input {
            border: 1px dashed #ddd;
            padding: 15px;
            text-align: center;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .attachments-list {
            margin-top: 10px;
        }
        
        .attachment-item {
            background-color: #f5f5f5;
            padding: 5px 10px;
            border-radius: 4px;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .remove-attachment {
            background-color: #e74c3c;
            color: white;
            border: none;
            width: auto;
            padding: 2px 5px;
            border-radius: 2px;
            cursor: pointer;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="ticket-form">
        <div class="form-header">
            <h2>Create Support Ticket</h2>
        </div>
        
        <form id="ticketForm" action="{{ route('ticket.iframe.submit') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <input type="hidden" name="token" value="{{ $token }}">
            
            <div class="form-group">
                <label for="name">Your Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" required>
                <div class="error-message" id="nameError"></div>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" required>
                <div class="error-message" id="emailError"></div>
            </div>
            
            <div class="form-group">
                <label for="subject">Subject <span class="required">*</span></label>
                <input type="text" id="subject" name="subject" required>
                <div class="error-message" id="subjectError"></div>
            </div>
            
            <div class="form-group">
                <label for="message">Message <span class="required">*</span></label>
                <textarea id="description" name="description" required></textarea>
                <div class="error-message" id="messageError"></div>
            </div>
            
            <!-- <div class="form-group">
                <label for="attachments">Attachments (optional)</label>
                <div class="file-input">
                    <input type="file" id="attachments" name="attachments[]" multiple>
                </div>
                <div class="attachments-list" id="attachmentsList"></div>
            </div> -->
            
            <div class="form-group">
                <button type="submit" id="submitButton">Submit Ticket</button>
            </div>
        </form>
        
        <div class="success-message" id="successMessage" style="display: none;">
            <h3>Thank you for your submission!</h3>
            <p>Your ticket has been created successfully. We'll get back to you as soon as possible.</p>
            <p id="ticketNumber"></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('ticketForm');
            const attachmentsInput = document.getElementById('attachments');
            const attachmentsList = document.getElementById('attachmentsList');
            const submitButton = document.getElementById('submitButton');
            const successMessage = document.getElementById('successMessage');
            const ticketNumberElement = document.getElementById('ticketNumber');
            
            // Handle file attachments display
            // attachmentsInput.addEventListener('change', function() {
            //     attachmentsList.innerHTML = '';
                
            //     for (const file of this.files) {
            //         const attachmentItem = document.createElement('div');
            //         attachmentItem.className = 'attachment-item';
            //         attachmentItem.innerHTML = `
            //             <span>${file.name} (${formatFileSize(file.size)})</span>
            //         `;
            //         attachmentsList.appendChild(attachmentItem);
            //     }
            // });
            
            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
                
                // Disable submit button and show loading state
                submitButton.disabled = true;
                submitButton.textContent = 'Submitting...';
                
                // Create form data
                const formData = new FormData(form);
                
                // Submit the form via AJAX
                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        form.style.display = 'none';
                        successMessage.style.display = 'block';
                        if (data.ticket_number) {
                            ticketNumberElement.textContent = `Your ticket number is: ${data.ticket_number}`;
                        }
                    } else {
                        // Show validation errors
                        if (data.errors) {
                            for (const [field, errors] of Object.entries(data.errors)) {
                                const errorElement = document.getElementById(`${field}Error`);
                                if (errorElement) {
                                    errorElement.textContent = errors[0];
                                }
                            }
                        }
                        submitButton.disabled = false;
                        submitButton.textContent = 'Submit Ticket';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    submitButton.disabled = false;
                    submitButton.textContent = 'Submit Ticket';
                });
            });
            
            // Helper function to format file size
            // function formatFileSize(bytes) {
            //     if (bytes === 0) return '0 Bytes';
                
            //     const k = 1024;
            //     const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            //     const i = Math.floor(Math.log(bytes) / Math.log(k));
                
            //     return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            // }
        });
    </script>
</body>
</html>