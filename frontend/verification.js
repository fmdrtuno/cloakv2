/**
 * Verification Logic (Refactored for Stability)
 *
 * This script handles the verification popup, user input,
 * and communication with the backend API proxy.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Find essential elements for verification
    const configDiv = document.getElementById('verification-config');
    const overlay = document.getElementById('verification-overlay');
    const form = document.getElementById('verification-form');

    // If the verification overlay doesn't exist, there's nothing to do.
    if (!overlay || !configDiv || !form) {
        return;
    }

    const verificationMode = parseInt(configDiv.dataset.verificationMode, 10);

    // If verification is disabled, do nothing.
    if (verificationMode === 0) {
        return;
    }

    // Get other interactive elements
    const answerInput = document.getElementById('verification-answer');
    const errorP = document.getElementById('verification-error');
    const submitButton = document.getElementById('verification-submit-button');
    const mainContent = document.querySelector('.main-container');

    // Ensure all required elements are present before proceeding
    if (!answerInput || !errorP || !submitButton) {
        return;
    }

    // Show the verification popup
    overlay.style.display = 'flex';

    // If mode is 1 (popup only), hide the main content completely
    if (verificationMode === 1 && mainContent) {
        mainContent.style.display = 'none';
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }

    /**
     * Handles the API request for verification.
     */
    const processVerification = async () => {
        const answer = answerInput.value.trim();
        if (!answer) {
            errorP.textContent = 'Vui lòng nhập câu trả lời.';
            errorP.style.display = 'block';
            return;
        }

        // Disable button to prevent multiple submissions
        submitButton.disabled = true;
        submitButton.textContent = 'Đang xử lý...';
        errorP.style.display = 'none';

        try {
            const postData = new URLSearchParams({
                action: 'check_verification',
                answer: answer
            });

            const response = await fetch('api_proxy.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: postData
            });

            const rawText = await response.text();
            let result;
            try {
                result = JSON.parse(rawText);
            } catch (e) {
                throw new Error(`Phản hồi không hợp lệ từ máy chủ: ${rawText}`);
            }
            
            if (result.success) {
                const redirectMode = configDiv.dataset.redirectMode === 'true';

                if (redirectMode && result.redirect_url) {
                    // If redirect mode is enabled, it takes priority.
                    window.location.href = result.redirect_url;
                } else if (verificationMode === 1) {
                    // In popup-only mode, hide the popup and show the main content.
                    if (overlay) overlay.style.display = 'none';
                    if (mainContent) mainContent.style.display = 'block';
                    document.body.style.overflow = 'auto'; // Restore scrolling
                }
                else {
                    // For overlay mode (or as a fallback), reload the page.
                    window.location.reload();
                }
            } else {
                // On failure, show the error message from the API
                throw new Error(result.error || 'Đáp án sai, hãy thử lại!');
            }
        } catch (err) {
            // Handle network errors or errors thrown from the try block
            errorP.textContent = err.message;
            errorP.style.display = 'block';
            // Re-enable the button so the user can try again
            submitButton.disabled = false;
            submitButton.textContent = 'Xác nhận';
        }
    };

    // Attach event listener to the button's click event.
    submitButton.addEventListener('click', function(e) {
        e.preventDefault(); // Prevent the default form submission
        processVerification();
    });
});
