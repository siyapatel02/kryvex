document.addEventListener('DOMContentLoaded', () => {
    setupWithdrawApproveModal();
});

function setupWithdrawApproveModal() {
    const modal = document.getElementById('withdrawApproveModal');
    const closeButton = document.getElementById('withdrawApproveClose');
    const cancelButton = document.getElementById('withdrawApproveCancel');
    const submitButton = document.getElementById('withdrawApproveSubmit');
    const copyButton = document.getElementById('withdrawCopyAddressButton');
    const confirmInput = document.getElementById('withdrawConfirmAddressInput');
    const help = document.getElementById('withdrawConfirmHelp');

    if (!modal || !submitButton || !confirmInput) {
        return;
    }

    let activeFormId = '';
    let expectedAddress = '';

    const setText = (id, value) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value || '--';
    };

    const validateAddress = () => {
        const pastedAddress = confirmInput.value.trim();
        const isValid = expectedAddress !== '' && pastedAddress === expectedAddress;

        submitButton.disabled = !isValid;
        confirmInput.classList.toggle('is-valid', isValid);
        confirmInput.classList.toggle('is-invalid', pastedAddress.length > 0 && !isValid);

        if (!pastedAddress) {
            help.textContent = 'Address must match exactly before approval.';
            help.className = 'withdraw-confirm-help';
        } else if (isValid) {
            help.textContent = 'Address matched. You can approve this withdrawal.';
            help.className = 'withdraw-confirm-help success';
        } else {
            help.textContent = 'Address does not match the user withdrawal address.';
            help.className = 'withdraw-confirm-help error';
        }
    };

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        activeFormId = '';
        expectedAddress = '';
        confirmInput.value = '';
        submitButton.disabled = true;
        help.textContent = 'Address must match exactly before approval.';
        help.className = 'withdraw-confirm-help';
    };

    document.querySelectorAll('[data-open-withdraw-approve-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            activeFormId = button.dataset.formId || '';
            expectedAddress = (button.dataset.address || '').trim();

            setText('withdrawModalUser', button.dataset.userName || '--');
            setText('withdrawModalEmail', button.dataset.userEmail || '--');
            setText('withdrawModalAmount', button.dataset.amount || '--');
            setText('withdrawModalFees', button.dataset.fees || '--');
            setText('withdrawModalNet', button.dataset.net || '--');
            setText('withdrawModalNetwork', button.dataset.network || '--');
            setText('withdrawModalAddress', expectedAddress || 'No address saved');

            confirmInput.value = '';
            validateAddress();
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            setTimeout(() => confirmInput.focus(), 80);
        });
    });

    confirmInput.addEventListener('input', validateAddress);

    copyButton?.addEventListener('click', async () => {
        if (!expectedAddress) return;

        try {
            await navigator.clipboard.writeText(expectedAddress);
            copyButton.textContent = 'Copied';
            setTimeout(() => {
                copyButton.textContent = 'Copy';
            }, 1200);
        } catch (error) {
            confirmInput.value = expectedAddress;
            validateAddress();
            confirmInput.select();
        }
    });

    submitButton.addEventListener('click', () => {
        validateAddress();

        if (submitButton.disabled || !activeFormId) {
            return;
        }

        const form = document.getElementById(activeFormId);
        const hiddenInput = form?.querySelector('[name="confirmed_wallet_address"]');

        if (!form || !hiddenInput) {
            return;
        }

        hiddenInput.value = confirmInput.value.trim();
        submitButton.disabled = true;
        submitButton.textContent = 'Approving...';
        form.submit();
    });

    [closeButton, cancelButton].forEach((button) => {
        button?.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
}
