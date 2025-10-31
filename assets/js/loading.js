/**
 * Loading State Management
 * Trou Idees Facebook Approval System
 *
 * Manages loading states for buttons and forms to provide visual feedback
 * during form submissions and async operations.
 *
 * FEATURES:
 * - Auto-attaches to all forms on page
 * - Prevents double-submission
 * - Shows spinner animation
 * - Restores button state on error
 *
 * USAGE:
 *   // Automatically works on all forms
 *   // Or manually:
 *   TrouIdees.loading.show(button);
 *   TrouIdees.loading.hide(button);
 */

(function() {
    'use strict';

    // Create namespace if it doesn't exist
    window.TrouIdees = window.TrouIdees || {};

    /**
     * Show Loading State on Button
     *
     * Disables button, stores original text, and shows loading spinner.
     *
     * @param {HTMLElement} button - The button element
     * @param {string} loadingText - Optional custom loading text (default: 'Processing...')
     */
    function show(button, loadingText = 'Processing...') {
        if (!button || button.disabled) return;

        // Store original state
        button.dataset.originalText = button.innerHTML;
        button.dataset.originalDisabled = button.disabled;

        // Set loading state
        button.disabled = true;
        button.classList.add('btn-loading');
        button.innerHTML = `<span class="spinner" aria-hidden="true"></span> ${loadingText}`;

        // Set ARIA attributes for accessibility
        button.setAttribute('aria-busy', 'true');
    }

    /**
     * Hide Loading State on Button
     *
     * Restores button to original state.
     *
     * @param {HTMLElement} button - The button element
     */
    function hide(button) {
        if (!button) return;

        // Restore original state
        const originalText = button.dataset.originalText;
        const originalDisabled = button.dataset.originalDisabled === 'true';

        if (originalText) {
            button.innerHTML = originalText;
        }

        button.disabled = originalDisabled;
        button.classList.remove('btn-loading');
        button.removeAttribute('aria-busy');

        // Clean up data attributes
        delete button.dataset.originalText;
        delete button.dataset.originalDisabled;
    }

    /**
     * Initialize Form Loading States
     *
     * Automatically attaches loading states to all forms.
     * Called on DOM ready and can be called manually for dynamically added forms.
     */
    function init() {
        // Find all forms
        const forms = document.querySelectorAll('form');

        forms.forEach(form => {
            // Skip if already initialized
            if (form.dataset.loadingInitialized) return;

            // Mark as initialized
            form.dataset.loadingInitialized = 'true';

            // Add submit handler
            form.addEventListener('submit', function(e) {
                // Find submit button
                const submitBtn = this.querySelector('button[type="submit"]');

                if (submitBtn && !submitBtn.disabled) {
                    // Check for custom loading text
                    const loadingText = submitBtn.dataset.loadingText || 'Processing...';

                    // Show loading state
                    show(submitBtn, loadingText);
                }
            });
        });
    }

    /**
     * Auto-Attach to Dynamically Added Forms
     *
     * Uses MutationObserver to watch for new forms added to the DOM.
     */
    function observeDynamicForms() {
        // Only run if MutationObserver is supported
        if (typeof MutationObserver === 'undefined') return;

        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    // Check if node is an element
                    if (node.nodeType !== 1) return;

                    // Check if node is a form or contains forms
                    if (node.tagName === 'FORM') {
                        initForm(node);
                    } else if (node.querySelectorAll) {
                        const forms = node.querySelectorAll('form');
                        forms.forEach(initForm);
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Initialize Single Form
     *
     * @param {HTMLElement} form - The form element
     */
    function initForm(form) {
        if (form.dataset.loadingInitialized) return;

        form.dataset.loadingInitialized = 'true';

        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');

            if (submitBtn && !submitBtn.disabled) {
                const loadingText = submitBtn.dataset.loadingText || 'Processing...';
                show(submitBtn, loadingText);
            }
        });
    }

    // Public API
    window.TrouIdees.loading = {
        show: show,
        hide: hide,
        init: init
    };

    /**
     * Auto-Initialize
     *
     * Runs on DOM ready to attach loading states to all forms.
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init();
            observeDynamicForms();
        });
    } else {
        init();
        observeDynamicForms();
    }

    /**
     * Inject Loading Styles
     *
     * Injects CSS for loading spinner and button states.
     */
    function injectStyles() {
        // Check if styles already injected
        if (document.getElementById('loading-styles')) return;

        const style = document.createElement('style');
        style.id = 'loading-styles';
        style.textContent = `
            /* Loading button state */
            button.btn-loading {
                position: relative;
                pointer-events: none;
                opacity: 0.8;
            }

            /* Spinner animation */
            .spinner {
                display: inline-block;
                width: 12px;
                height: 12px;
                border: 2px solid rgba(255, 255, 255, 0.3);
                border-top-color: white;
                border-radius: 50%;
                animation: spinner-rotate 0.6s linear infinite;
                vertical-align: middle;
                margin-right: 6px;
            }

            /* Spinner in non-white buttons */
            .btn-secondary .spinner,
            .btn-neutral .spinner {
                border-color: rgba(0, 0, 0, 0.2);
                border-top-color: #666;
            }

            @keyframes spinner-rotate {
                to {
                    transform: rotate(360deg);
                }
            }

            /* Disabled state for all buttons */
            button:disabled,
            .btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                pointer-events: none;
            }
        `;
        document.head.appendChild(style);
    }

    // Inject styles immediately
    injectStyles();

})();
