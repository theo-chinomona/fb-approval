/**
 * Toast Notification System
 * Trou Idees Facebook Approval System
 *
 * Modern, non-intrusive notification system for user feedback.
 * Replaces browser alerts and query-string success messages with
 * elegant, auto-dismissing toast notifications.
 *
 * USAGE:
 *   TrouIdees.toast.show('Message saved!', 'success');
 *   TrouIdees.toast.show('An error occurred', 'error');
 *   TrouIdees.toast.show('Please review', 'warning');
 *   TrouIdees.toast.show('FYI: System maintenance tonight', 'info');
 */

(function() {
    'use strict';

    // Create namespace if it doesn't exist
    window.TrouIdees = window.TrouIdees || {};

    /**
     * Toast Configuration
     */
    const config = {
        duration: 5000,        // Auto-dismiss after 5 seconds
        maxToasts: 3,          // Maximum simultaneous toasts
        position: 'top-right', // top-right, top-left, bottom-right, bottom-left
        animationDuration: 300 // ms
    };

    /**
     * Toast Icons by Type
     */
    const icons = {
        success: '✓',
        error: '✗',
        warning: '⚠',
        info: 'ℹ'
    };

    /**
     * Create Toast Container
     *
     * Creates the container element that holds all toasts.
     * Called once on first toast.
     */
    function createContainer() {
        let container = document.getElementById('toast-container');

        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'false');
            document.body.appendChild(container);
        }

        return container;
    }

    /**
     * Show Toast Notification
     *
     * @param {string} message - The message to display
     * @param {string} type - Toast type: 'success', 'error', 'warning', 'info'
     * @param {object} options - Optional configuration overrides
     * @returns {HTMLElement} The toast element
     */
    function show(message, type = 'success', options = {}) {
        // Validate type
        if (!['success', 'error', 'warning', 'info'].includes(type)) {
            console.warn(`Invalid toast type: ${type}. Using 'info' instead.`);
            type = 'info';
        }

        // Merge options with defaults
        const opts = Object.assign({}, config, options);

        // Get or create container
        const container = createContainer();

        // Limit number of toasts
        const existingToasts = container.querySelectorAll('.toast');
        if (existingToasts.length >= opts.maxToasts) {
            // Remove oldest toast
            hideToast(existingToasts[0]);
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');

        // Create toast HTML
        toast.innerHTML = `
            <div class="toast-icon" aria-hidden="true">${icons[type]}</div>
            <div class="toast-message">${escapeHtml(message)}</div>
            <button class="toast-close" aria-label="Close notification">×</button>
        `;

        // Add to container
        container.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => {
            setTimeout(() => {
                toast.classList.add('toast-show');
            }, 10);
        });

        // Set up close button
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => hideToast(toast));

        // Auto-dismiss
        if (opts.duration > 0) {
            setTimeout(() => {
                if (toast.parentElement) {
                    hideToast(toast);
                }
            }, opts.duration);
        }

        return toast;
    }

    /**
     * Hide Toast
     *
     * Animates toast out and removes it from DOM.
     *
     * @param {HTMLElement} toast - The toast element to hide
     */
    function hideToast(toast) {
        if (!toast || !toast.parentElement) return;

        toast.classList.remove('toast-show');

        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, config.animationDuration);
    }

    /**
     * Success Toast Shortcut
     *
     * @param {string} message
     */
    function success(message) {
        return show(message, 'success');
    }

    /**
     * Error Toast Shortcut
     *
     * @param {string} message
     */
    function error(message) {
        return show(message, 'error');
    }

    /**
     * Warning Toast Shortcut
     *
     * @param {string} message
     */
    function warning(message) {
        return show(message, 'warning');
    }

    /**
     * Info Toast Shortcut
     *
     * @param {string} message
     */
    function info(message) {
        return show(message, 'info');
    }

    /**
     * Escape HTML
     *
     * Prevents XSS by escaping HTML entities in user-provided text.
     *
     * @param {string} text
     * @returns {string}
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Clear All Toasts
     *
     * Removes all visible toasts.
     */
    function clearAll() {
        const container = document.getElementById('toast-container');
        if (container) {
            const toasts = container.querySelectorAll('.toast');
            toasts.forEach(toast => hideToast(toast));
        }
    }

    // Public API
    window.TrouIdees.toast = {
        show: show,
        success: success,
        error: error,
        warning: warning,
        info: info,
        clearAll: clearAll
    };

    /**
     * Initialize Toast System
     *
     * Checks for URL parameters and shows toasts accordingly.
     * This replaces the old success notice system.
     */
    function init() {
        // Check for 'updated' parameter (from form submissions)
        const urlParams = new URLSearchParams(window.location.search);
        const updated = urlParams.get('updated');

        if (updated) {
            const count = parseInt(updated, 10);
            if (!isNaN(count) && count > 0) {
                success(`${count} submission${count !== 1 ? 's' : ''} updated successfully!`);
            }

            // Clean URL without reloading
            const url = new URL(window.location);
            url.searchParams.delete('updated');
            window.history.replaceState({}, '', url.toString());
        }

        // Check for 'error' parameter
        const errorMsg = urlParams.get('error');
        if (errorMsg) {
            error(decodeURIComponent(errorMsg));

            // Clean URL
            const url = new URL(window.location);
            url.searchParams.delete('error');
            window.history.replaceState({}, '', url.toString());
        }

        // Check for 'success' parameter
        const successMsg = urlParams.get('success');
        if (successMsg) {
            success(decodeURIComponent(successMsg));

            // Clean URL
            const url = new URL(window.location);
            url.searchParams.delete('success');
            window.history.replaceState({}, '', url.toString());
        }
    }

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /**
     * Inject Toast Styles
     *
     * Injects CSS directly into the page for toast styling.
     * This ensures toasts work even if external CSS fails to load.
     */
    function injectStyles() {
        // Check if styles already injected
        if (document.getElementById('toast-styles')) return;

        const style = document.createElement('style');
        style.id = 'toast-styles';
        style.textContent = `
            .toast-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                pointer-events: none;
            }

            .toast {
                min-width: 300px;
                max-width: 500px;
                background: white;
                padding: 16px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15), 0 0 0 1px rgba(0,0,0,0.05);
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 12px;
                transform: translateX(400px);
                opacity: 0;
                transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                pointer-events: auto;
            }

            .toast.toast-show {
                transform: translateX(0);
                opacity: 1;
            }

            .toast-icon {
                flex-shrink: 0;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 16px;
                line-height: 1;
            }

            .toast-success .toast-icon {
                background: #e8f5e9;
                color: #2e7d32;
            }

            .toast-error .toast-icon {
                background: #ffebee;
                color: #c62828;
            }

            .toast-warning .toast-icon {
                background: #fff3e0;
                color: #e65100;
            }

            .toast-info .toast-icon {
                background: #e3f2fd;
                color: #1976d2;
            }

            .toast-message {
                flex: 1;
                font-size: 14px;
                color: #333;
                line-height: 1.4;
                word-break: break-word;
            }

            .toast-close {
                flex-shrink: 0;
                background: none;
                border: none;
                font-size: 24px;
                line-height: 1;
                color: #999;
                cursor: pointer;
                padding: 0;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 4px;
                transition: all 0.2s;
            }

            .toast-close:hover {
                background: #f0f0f0;
                color: #666;
            }

            .toast-close:focus-visible {
                outline: 2px solid #d4849c;
                outline-offset: 2px;
            }

            /* Mobile adjustments */
            @media (max-width: 640px) {
                .toast-container {
                    left: 10px;
                    right: 10px;
                    top: 10px;
                }

                .toast {
                    min-width: 0;
                    max-width: none;
                }
            }
        `;
        document.head.appendChild(style);
    }

    // Inject styles immediately
    injectStyles();

})();
