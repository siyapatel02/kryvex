// Admin settings page tabs + live preview.
// This file only controls the settings page UI; the form still submits normally to Laravel.
document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('[data-settings-page]');

    if (!page) {
        return;
    }

    const tabs = page.querySelectorAll('[data-settings-tab]');
    const panels = page.querySelectorAll('[data-settings-panel]');

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.settingsTab;

            tabs.forEach((item) => item.classList.toggle('is-active', item === tab));
            panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.settingsPanel === target));
        });
    });

    const form = page.querySelector('.settings-form');
    const exchangeInput = form?.querySelector('[name="exchange_name"]');
    const supportInput = form?.querySelector('[name="support_email"]');
    const previewName = document.getElementById('settingsPreviewName');
    const previewEmail = document.getElementById('settingsPreviewEmail');

    const updatePlatformPreview = () => {
        if (previewName && exchangeInput) {
            previewName.textContent = exchangeInput.value.trim() || 'Kryvex Exchange';
        }

        if (previewEmail && supportInput) {
            previewEmail.textContent = supportInput.value.trim() || 'support@kryvex.com';
        }
    };

    exchangeInput?.addEventListener('input', updatePlatformPreview);
    supportInput?.addEventListener('input', updatePlatformPreview);

    const chargeInputs = form?.querySelectorAll('[data-charge-input]') || [];
    const chargePreview = document.getElementById('withdrawChargePreview');

    const calculateCharge = (amount, type, value) => {
        const numericValue = Number(value || 0);

        if (type === 'percentage') {
            return amount * (numericValue / 100);
        }

        return numericValue;
    };

    const updateChargePreview = () => {
        if (!chargePreview || !form) {
            return;
        }

        const sampleAmount = 100;
        const withdrawalFeeType = form.querySelector('[name="withdrawal_fee_type"]')?.value || 'percentage';
        const withdrawalFeeValue = form.querySelector('[name="withdrawal_fee_value"]')?.value || 0;
        const platformChargeType = form.querySelector('[name="platform_charge_type"]')?.value || 'fixed';
        const platformChargeValue = form.querySelector('[name="platform_charge_value"]')?.value || 0;

        const withdrawalFee = calculateCharge(sampleAmount, withdrawalFeeType, withdrawalFeeValue);
        const platformCharge = calculateCharge(sampleAmount, platformChargeType, platformChargeValue);
        const netAmount = Math.max(0, sampleAmount - withdrawalFee - platformCharge);

        chargePreview.textContent = `$${netAmount.toFixed(2)}`;
    };

    chargeInputs.forEach((input) => {
        input.addEventListener('input', updateChargePreview);
        input.addEventListener('change', updateChargePreview);
    });

    updatePlatformPreview();
    updateChargePreview();
});
