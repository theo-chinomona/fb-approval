/**
 * Custom Modal Dialog System
 * Trou Idees Facebook Approval System
 *
 * Replaces browser confirm() dialogs with branded, accessible modals.
 * Provides better UX with contextual messaging and animations.
 *
 * USAGE:
 *   TrouIdees.modal.confirm({
 *       title: 'Delete Submission',
 *       message: 'Are you sure you want to delete this submission?',
 *       confirmText: 'Delete',
 *       confirmClass: 'btn-delete',
 *       onConfirm: () => { // do something }
 *   });
 */

(function() {
    'use strict';

    // Create namespace if it doesn't exist
    window.TrouIdees = window.TrouIdees || {};

    /**
     * Show Confirmation Modal
     *
     * @param {object} options - Configuration options
     * @param {string} options.title - Modal title
     * @param {string} options.message - Modal message body
     * @param {string} options.confirmText - Text for confirm button (default: 'Confirm')
     * @param {string} options.cancelText - Text for cancel button (default: 'Cancel')
     * @param {string} options.confirmClass - CSS class for confirm button (default: 'btn-primary')
     * @param {function} options.onConfirm - Callback when confirmed
     * @param {function} options.onCancel - Callback when cancelled (optional)
     * @returns {HTMLElement} The modal overlay element
     */
    function confirm(options) {
        // Default options
        const defaults = {
            title: 'Confirm Action',
            message: 'Are you sure?',
            confirmText: 'Confirm',
            cancelText: 'Cancel',
            confirmClass: 'btn-primary',
            onConfirm: null,
            onCancel: null
        };

        const opts = Object.assign({}, defaults, options);

        // Create modal overlay
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'modal-title');
        overlay.setAttribute('aria-describedby', 'modal-description');

        // Create modal HTML
        overlay.innerHTML = `
            <div class="modal-container">
                <div class="modal-header">
                    <h3 id="modal-title">${escapeHtml(opts.title)}</h3>
                    <button class="modal-close" aria-label="Close dialog" type="button">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="modal-description">${escapeHtml(opts.message)}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary modal-cancel" type="button">
                        ${escapeHtml(opts.cancelText)}
                    </button>
                    <button class="btn ${opts.confirmClass} modal-confirm" type="button">
                        ${escapeHtml(opts.confirmText)}
                    </button>
                </div>
            </div>
        `;

        // Add to body
        document.body.appendChild(overlay);

        // Get elements
        const container = overlay.querySelector('.modal-container');
        const closeBtn = overlay.querySelector('.modal-close');
        const cancelBtn = overlay.querySelector('.modal-cancel');
        const confirmBtn = overlay.querySelector('.modal-confirm');

        // Store currently focused element to restore later
        const previouslyFocused = document.activeElement;

        // Animate in
        requestAnimationFrame(() => {
            setTimeout(() => {
                overlay.classList.add('modal-show');
            }, 10);
        });

        // Focus the confirm button
        setTimeout(() => {
            confirmBtn.focus();
        }, 100);

        // Trap focus within modal
        trapFocus(container);

        // Close function
        function closeModal() {
            overlay.classList.remove('modal-show');

            setTimeout(() => {
                overlay.remove();
                // Restore focus to previously focused element
                if (previouslyFocused && previouslyFocused.focus) {
                    previouslyFocused.focus();
                }
            }, 300);
        }

        // Event: Close button
        closeBtn.addEventListener('click', function() {
            closeModal();
            if (typeof opts.onCancel === 'function') {
                opts.onCancel();
            }
        });

        // Event: Cancel button
        cancelBtn.addEventListener('click', function() {
            closeModal();
            if (typeof opts.onCancel === 'function') {
                opts.onCancel();
            }
        });

        // Event: Confirm button
        confirmBtn.addEventListener('click', function() {
            closeModal();
            if (typeof opts.onConfirm === 'function') {
                opts.onConfirm();
            }
        });

        // Event: Click outside to close
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closeModal();
                if (typeof opts.onCancel === 'function') {
                    opts.onCancel();
                }
            }
        });

        // Event: ESC key to close
        function escHandler(e) {
            if (e.key === 'Escape') {
                closeModal();
                if (typeof opts.onCancel === 'function') {
                    opts.onCancel();
                }
                document.removeEventListener('keydown', escHandler);
            }
        }
        document.addEventListener('keydown', escHandler);

        return overlay;
    }

    /**
     * Show Alert Modal (OK only, no cancel)
     *
     * @param {object} options - Configuration options
     * @param {string} options.title - Modal title
     * @param {string} options.message - Modal message body
     * @param {string} options.buttonText - Text for OK button (default: 'OK')
     * @param {function} options.onClose - Callback when closed (optional)
     */
    function alert(options) {
        const defaults = {
            title: 'Notice',
            message: '',
            buttonText: 'OK',
            onClose: null
        };

        const opts = Object.assign({}, defaults, options);

        // Create modal overlay
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'modal-title');
        overlay.setAttribute('aria-describedby', 'modal-description');

        // Create modal HTML
        overlay.innerHTML = `
            <div class="modal-container">
                <div class="modal-header">
                    <h3 id="modal-title">${escapeHtml(opts.title)}</h3>
                </div>
                <div class="modal-body">
                    <p id="modal-description">${escapeHtml(opts.message)}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary modal-ok" type="button">
                        ${escapeHtml(opts.buttonText)}
                    </button>
                </div>
            </div>
        `;

        // Add to body
        document.body.appendChild(overlay);

        // Get elements
        const container = overlay.querySelector('.modal-container');
        const okBtn = overlay.querySelector('.modal-ok');

        // Store currently focused element
        const previouslyFocused = document.activeElement;

        // Animate in
        requestAnimationFrame(() => {
            setTimeout(() => {
                overlay.classList.add('modal-show');
            }, 10);
        });

        // Focus OK button
        setTimeout(() => {
            okBtn.focus();
        }, 100);

        // Trap focus
        trapFocus(container);

        // Close function
        function closeModal() {
            overlay.classList.remove('modal-show');

            setTimeout(() => {
                overlay.remove();
                // Restore focus
                if (previouslyFocused && previouslyFocused.focus) {
                    previouslyFocused.focus();
                }
            }, 300);

            if (typeof opts.onClose === 'function') {
                opts.onClose();
            }
        }

        // Event: OK button
        okBtn.addEventListener('click', closeModal);

        // Event: ESC key
        function escHandler(e) {
            if (e.key === 'Escape') {
                closeModal();
                document.removeEventListener('keydown', escHandler);
            }
        }
        document.addEventListener('keydown', escHandler);

        return overlay;
    }

    /**
     * Trap Focus Within Element
     *
     * Ensures keyboard focus stays within modal (accessibility requirement).
     *
     * @param {HTMLElement} element - The element to trap focus within
     */
    function trapFocus(element) {
        const focusableElements = element.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );

        if (focusableElements.length === 0) return;

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        function handleTab(e) {
            if (e.key !== 'Tab') return;

            if (e.shiftKey) {
                // Shift + Tab
                if (document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                }
            } else {
                // Tab
                if (document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        }

        element.addEventListener('keydown', handleTab);
    }

    /**
     * Escape HTML
     *
     * Prevents XSS by escaping HTML entities.
     *
     * @param {string} text
     * @returns {string}
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Public API
    window.TrouIdees.modal = {
        confirm: confirm,
        alert: alert
    };

    /**
     * Inject Modal Styles
     *
     * Injects CSS for modals directly into the page.
     */
    function injectStyles() {
        // Check if styles already injected
        if (document.getElementById('modal-styles')) return;

        const style = document.createElement('style');
        style.id = 'modal-styles';
        style.textContent = `
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.3s;
                backdrop-filter: blur(4px);
            }

            .modal-overlay.modal-show {
                opacity: 1;
            }

            .modal-container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 500px;
                width: 90%;
                max-height: 90vh;
                overflow: hidden;
                transform: scale(0.9);
                transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            }

            .modal-overlay.modal-show .modal-container {
                transform: scale(1);
            }

            .modal-header {
                padding: 20px 24px;
                border-bottom: 1px solid #f0e6ea;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .modal-header h3 {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
                color: #333;
            }

            .modal-close {
                background: none;
                border: none;
                font-size: 28px;
                line-height: 1;
                color: #999;
                cursor: pointer;
                padding: 0;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 4px;
                transition: all 0.2s;
            }

            .modal-close:hover {
                background: #f0f0f0;
                color: #666;
            }

            .modal-close:focus-visible {
                outline: 2px solid #d4849c;
                outline-offset: 2px;
            }

            .modal-body {
                padding: 24px;
                overflow-y: auto;
                max-height: calc(90vh - 160px);
            }

            .modal-body p {
                margin: 0;
                font-size: 15px;
                line-height: 1.6;
                color: #555;
            }

            .modal-footer {
                padding: 16px 24px;
                border-top: 1px solid #f0e6ea;
                display: flex;
                justify-content: flex-end;
                gap: 12px;
            }

            .modal-footer .btn {
                padding: 10px 20px;
                border: none;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s;
            }

            .modal-footer .btn-secondary {
                background: #f5f5f5;
                color: #666;
            }

            .modal-footer .btn-secondary:hover {
                background: #e0e0e0;
            }

            .modal-footer .btn-primary {
                background: #d4849c;
                color: white;
            }

            .modal-footer .btn-primary:hover {
                background: #c26d88;
            }

            .modal-footer .btn:focus-visible {
                outline: 2px solid #d4849c;
                outline-offset: 2px;
            }

            /* Mobile adjustments */
            @media (max-width: 640px) {
                .modal-container {
                    width: 100%;
                    height: 100%;
                    max-width: none;
                    max-height: none;
                    border-radius: 0;
                }

                .modal-overlay {
                    padding: 0;
                    align-items: flex-start;
                }

                .modal-body {
                    max-height: calc(100vh - 160px);
                }

                .modal-footer {
                    flex-direction: column;
                }

                .modal-footer .btn {
                    width: 100%;
                }
            }
        `;
        document.head.appendChild(style);
    }

    // Inject styles immediately
    injectStyles();

})();
