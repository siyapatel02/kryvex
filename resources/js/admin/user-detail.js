document.addEventListener('DOMContentLoaded', () => {
    /**
     * Admin user detail page behavior.
     *
     * What this file controls:
     * - Suspend schedule modal open / close.
     * - Block schedule modal open / close.
     * - No inline JS is used in Blade for these modals.
     * - Global confirmation modals are still handled by resources/js/admin/layout.js.
     */
    const setupRestrictionModal = ({ modalId, openButtonId, closeSelector }) => {
        const modal = document.getElementById(modalId);
        const openButton = document.getElementById(openButtonId);
        const closeButtons = document.querySelectorAll(closeSelector);

        if (!modal || !openButton) {
            return;
        }

        const openModal = () => {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');

            const textarea = modal.querySelector('textarea[name="reason"]');
            if (textarea) {
                setTimeout(() => textarea.focus(), 120);
            }
        };

        const closeModal = () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        };

        openButton.addEventListener('click', openModal);

        closeButtons.forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    };

    setupRestrictionModal({
        modalId: 'suspendUserModal',
        openButtonId: 'openSuspendUserModal',
        closeSelector: '[data-close-suspend-modal]',
    });

    setupRestrictionModal({
        modalId: 'blockUserModal',
        openButtonId: 'openBlockUserModal',
        closeSelector: '[data-close-block-modal]',
    });

    setupRestrictionModal({
        modalId: 'resetCurrencyModal',
        openButtonId: 'openResetCurrencyModal',
        closeSelector: '[data-close-reset-modal]',
    });

    setupRestrictionModal({
        modalId: 'changeCurrencyModal',
        openButtonId: 'openChangeCurrencyModal',
        closeSelector: '[data-close-change-modal]',
    });
});
