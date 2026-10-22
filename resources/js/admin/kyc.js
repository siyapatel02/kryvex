document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('kycDetailModal');

    if (!modal) {
        return;
    }

    const setText = (selector, value) => {
        const node = modal.querySelector(selector);
        if (node) node.textContent = value || '—';
    };

    const setImage = (selector, emptySelector, url) => {
        const image = modal.querySelector(selector);
        const empty = modal.querySelector(emptySelector);

        if (!image || !empty) return;

        if (url) {
            image.src = url;
            image.style.display = 'block';
            empty.style.display = 'none';
        } else {
            image.removeAttribute('src');
            image.style.display = 'none';
            empty.style.display = 'block';
        }
    };

    const openModal = (button) => {
        setText('[data-kyc-name]', button.dataset.name);
        setText('[data-kyc-email]', button.dataset.email);
        setText('[data-kyc-uid]', button.dataset.userUid);
        setText('[data-kyc-status]', button.dataset.status);
        setText('[data-kyc-aadhaar-num]', button.dataset.aadhaarNumber);
        setText('[data-kyc-pan-num]', button.dataset.panNumber);
        setText('[data-kyc-ocr]', button.dataset.ocr);

        setImage('[data-kyc-pan-img]', '[data-kyc-pan-empty]', button.dataset.panImg || '');
        setImage('[data-kyc-selfie]', '[data-kyc-selfie-empty]', button.dataset.selfie || '');

        const userUid = button.dataset.userUid;
        const approveForm = modal.querySelector('#approveKycForm');
        const rejectForm = modal.querySelector('#rejectKycForm');
        if (approveForm) {
            approveForm.action = `/admin/kyc/${userUid}/approve`;
        }
        if (rejectForm) {
            rejectForm.action = `/admin/kyc/${userUid}/reject`;
        }

        const btnViewAadhaar = modal.querySelector('#btnViewAadhaarPdf');
        const btnDownloadAadhaar = modal.querySelector('#btnDownloadAadhaarPdf');
        const btnViewPan = modal.querySelector('#btnViewPanPdf');
        const btnDownloadPan = modal.querySelector('#btnDownloadPanPdf');

        if (btnViewAadhaar) btnViewAadhaar.href = `/admin/kyc/${userUid}/download/aadhaar?action=view`;
        if (btnDownloadAadhaar) btnDownloadAadhaar.href = `/admin/kyc/${userUid}/download/aadhaar?action=download`;
        if (btnViewPan) btnViewPan.href = `/admin/kyc/${userUid}/download/pan?action=view`;
        if (btnDownloadPan) btnDownloadPan.href = `/admin/kyc/${userUid}/download/pan?action=download`;

        const actionButtons = modal.querySelector('#kycActionButtons');
        if (actionButtons) {
            const status = (button.dataset.status || '').toLowerCase();
            if (status === 'pending review' || status === 'pending_review') {
                actionButtons.style.display = 'flex';
            } else {
                actionButtons.style.display = 'none';
            }
        }

        const reasonWrap = modal.querySelector('[data-kyc-reason-wrap]');
        const reason = modal.querySelector('[data-kyc-reason]');
        const reasonText = button.dataset.reason || '';

        if (reasonWrap && reason) {
            reasonWrap.hidden = !reasonText;
            reason.textContent = reasonText;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    document.querySelectorAll('[data-open-kyc-detail]').forEach((button) => {
        button.addEventListener('click', () => openModal(button));
    });

    modal.querySelectorAll('[data-close-kyc-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
});
