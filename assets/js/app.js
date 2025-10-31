/**
 * Main Application JavaScript
 * Trou Idees Facebook Approval System
 *
 * Handles all interactive functionality for the approval interface.
 *
 * FEATURES:
 * - Message truncation/expansion
 * - Batch selection and actions
 * - Confirmation modals
 * - Keyboard shortcuts
 * - Loading states (via loading.js)
 * - Toast notifications (via toast.js)
 */

(function() {
    'use strict';

    /* ========================================================================
     * MESSAGE TRUNCATION
     * ======================================================================== */

    /**
     * Initialize message truncation
     *
     * Automatically collapses long messages to 3 lines with "Show more" button.
     */
    function initMessageTruncation() {
        const messageContainers = document.querySelectorAll('.message-container');

        messageContainers.forEach(container => {
            const msg = container.querySelector('.message');
            const btn = container.querySelector('.show-more-btn');

            if (!msg || !btn) return;

            // Calculate if content exceeds 3 lines
            const lineHeight = parseFloat(window.getComputedStyle(msg).lineHeight);
            const maxHeight = lineHeight * 3;

            // Temporarily show full content to measure
            const originalMaxHeight = msg.style.maxHeight;
            msg.style.maxHeight = 'none';
            const fullHeight = msg.scrollHeight;
            msg.style.maxHeight = originalMaxHeight;

            // Check if message needs truncation (with small buffer for rounding)
            if (fullHeight > maxHeight + 2) {
                msg.classList.add('collapsed');
                btn.style.display = 'inline-block';
            } else {
                btn.style.display = 'none';
            }
        });
    }

    /**
     * Toggle message expansion
     *
     * @param {string} id - The submission ID
     */
    window.toggleMessage = function(id) {
        const message = document.getElementById('message-' + id);
        const btn = document.getElementById('btn-' + id);

        if (!message || !btn) return;

        if (message.classList.contains('collapsed')) {
            message.classList.remove('collapsed');
            btn.textContent = 'Show less ↑';
            btn.classList.add('show-less');
        } else {
            message.classList.add('collapsed');
            btn.textContent = 'Show more ↓';
            btn.classList.remove('show-less');
        }
    };

    /* ========================================================================
     * BATCH SELECTION
     * ======================================================================== */

    /**
     * Update batch controls visibility and selected count
     */
    function updateBatchControls() {
        const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
        const count = checkedBoxes.length;
        const selectedCountSpan = document.getElementById('selected-count');
        const batchControls = document.getElementById('batch-controls');

        if (selectedCountSpan) {
            selectedCountSpan.textContent = count;
        }

        if (batchControls) {
            if (count > 0) {
                batchControls.classList.add('active');
            } else {
                batchControls.classList.remove('active');
            }
        }

        // Update submission visual state
        document.querySelectorAll('.submission').forEach(sub => {
            const checkbox = sub.querySelector('.item-checkbox');
            if (checkbox && checkbox.checked) {
                sub.classList.add('selected');
            } else {
                sub.classList.remove('selected');
            }
        });
    }

    /**
     * Initialize batch selection
     */
    function initBatchSelection() {
        const selectAllCheckbox = document.getElementById('select-all');
        const itemCheckboxes = document.querySelectorAll('.item-checkbox');

        // Select all checkbox
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                itemCheckboxes.forEach(cb => {
                    cb.checked = this.checked;
                });
                updateBatchControls();
            });
        }

        // Individual checkboxes
        itemCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                updateBatchControls();

                // Update select-all checkbox
                const allChecked = Array.from(itemCheckboxes).every(checkbox => checkbox.checked);
                const someChecked = Array.from(itemCheckboxes).some(checkbox => checkbox.checked);

                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = someChecked && !allChecked;
                }
            });
        });
    }

    /**
     * Submit batch action
     *
     * @param {string} action - The batch action (batch_approve, batch_reject, etc.)
     */
    window.submitBatchAction = function(action) {
        const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
        const count = checkedBoxes.length;
        const batchForm = document.getElementById('batch-form');
        const batchActionInput = document.getElementById('batch-action');
        const batchIdsContainer = document.getElementById('batch-ids-container');

        if (count === 0) {
            if (window.TrouIdees && window.TrouIdees.toast) {
                window.TrouIdees.toast.warning('Please select at least one submission');
            } else {
                alert('Please select at least one submission');
            }
            return;
        }

        // Confirmation messages by action type
        const confirmations = {
            'batch_approve': {
                title: 'Approve Submissions',
                message: `Approve ${count} submission(s)?`,
                confirmText: 'Approve',
                confirmClass: 'btn-approve'
            },
            'batch_reject': {
                title: 'Reject Submissions',
                message: `Reject ${count} submission(s)? This cannot be undone.`,
                confirmText: 'Reject',
                confirmClass: 'btn-reject'
            },
            'batch_publish': {
                title: 'Publish to Facebook',
                message: `Publish ${count} submission(s) to Facebook? This will post them publicly.`,
                confirmText: 'Publish Now',
                confirmClass: 'btn-publish'
            },
            'batch_delete': {
                title: 'Delete Permanently',
                message: `Delete ${count} submission(s) permanently? This cannot be undone.`,
                confirmText: 'Delete',
                confirmClass: 'btn-delete'
            }
        };

        const config = confirmations[action] || {
            title: 'Confirm Action',
            message: `Perform action on ${count} submission(s)?`,
            confirmText: 'Confirm',
            confirmClass: 'btn-primary'
        };

        // Use custom modal if available, otherwise use browser confirm
        if (window.TrouIdees && window.TrouIdees.modal) {
            window.TrouIdees.modal.confirm({
                title: config.title,
                message: config.message,
                confirmText: config.confirmText,
                confirmClass: config.confirmClass,
                onConfirm: function() {
                    submitBatch();
                }
            });
        } else {
            if (confirm(config.message)) {
                submitBatch();
            }
        }

        function submitBatch() {
            // Clear previous hidden inputs
            if (batchIdsContainer) {
                batchIdsContainer.innerHTML = '';

                // Add hidden inputs for selected IDs
                checkedBoxes.forEach(cb => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = cb.dataset.id;
                    batchIdsContainer.appendChild(input);
                });
            }

            // Set action
            if (batchActionInput) {
                batchActionInput.value = action;
            }

            // Submit form
            if (batchForm) {
                batchForm.submit();
            }
        }
    };

    /* ========================================================================
     * ENHANCED DELETE CONFIRMATIONS
     * ======================================================================== */

    /**
     * Enhance delete buttons with custom modals
     */
    function enhanceDeleteButtons() {
        document.querySelectorAll('.btn-delete').forEach(btn => {
            // Skip if already enhanced
            if (btn.dataset.enhanced) return;

            // Only enhance buttons with onclick="return confirm(...)"
            const onclickAttr = btn.getAttribute('onclick');
            if (!onclickAttr || !onclickAttr.includes('confirm')) return;

            // Mark as enhanced
            btn.dataset.enhanced = 'true';

            // Remove onclick attribute
            btn.removeAttribute('onclick');

            // Add click handler
            btn.addEventListener('click', function(e) {
                e.preventDefault();

                if (window.TrouIdees && window.TrouIdees.modal) {
                    window.TrouIdees.modal.confirm({
                        title: 'Delete Submission',
                        message: 'Delete this submission permanently? This action cannot be undone.',
                        confirmText: 'Delete',
                        confirmClass: 'btn-delete',
                        onConfirm: function() {
                            // Submit the parent form
                            btn.closest('form').submit();
                        }
                    });
                } else {
                    // Fallback to browser confirm
                    if (confirm('Delete this submission permanently?')) {
                        btn.closest('form').submit();
                    }
                }
            });
        });
    }

    /* ========================================================================
     * KEYBOARD SHORTCUTS
     * ======================================================================== */

    /**
     * Initialize keyboard shortcuts
     */
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ignore when typing in inputs
            if (e.target.matches('input, textarea, select')) return;

            switch(e.key) {
                case 'a':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        const selectAll = document.getElementById('select-all');
                        if (selectAll) {
                            selectAll.checked = true;
                            selectAll.dispatchEvent(new Event('change'));
                        }
                    }
                    break;

                case 'Escape':
                    // Deselect all
                    const itemCheckboxes = document.querySelectorAll('.item-checkbox:checked');
                    itemCheckboxes.forEach(cb => cb.checked = false);
                    updateBatchControls();

                    const selectAll = document.getElementById('select-all');
                    if (selectAll) {
                        selectAll.checked = false;
                        selectAll.indeterminate = false;
                    }
                    break;

                case '?':
                    // Show keyboard shortcuts help
                    if (window.TrouIdees && window.TrouIdees.modal) {
                        window.TrouIdees.modal.alert({
                            title: 'Keyboard Shortcuts',
                            message: 'Ctrl/Cmd + A: Select all submissions\nEscape: Deselect all\n?: Show this help',
                            buttonText: 'Got it'
                        });
                    }
                    break;
            }
        });
    }

    /* ========================================================================
     * INITIALIZATION
     * ======================================================================== */

    /**
     * Initialize all functionality
     */
    function init() {
        initMessageTruncation();
        initBatchSelection();
        enhanceDeleteButtons();
        initKeyboardShortcuts();
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
