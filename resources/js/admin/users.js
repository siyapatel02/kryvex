const usersLiveSearch = document.getElementById('usersLiveSearch');
const usersFilterForm = document.getElementById('usersFilterForm');

if (usersLiveSearch && usersFilterForm) {
    let searchTimer = null;

    usersLiveSearch.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            const value = usersLiveSearch.value.trim();
            if (value.length >= 2 || value.length === 0) {
                usersFilterForm.submit();
            }
        }, 700);
    });
}

function setupUserIndexStatusModal({
    modalId,
    formId,
    openSelector,
    closeSelector,
    userNameSelector,
}) {
    const modal = document.getElementById(modalId);
    const form = document.getElementById(formId);
    const openButtons = document.querySelectorAll(openSelector);
    const closeButtons = document.querySelectorAll(closeSelector);
    const userNameTarget = document.querySelector(userNameSelector);

    if (!modal || !form || !openButtons.length) {
        return;
    }

    const openModal = (button) => {
        const actionUrl = button.dataset.statusUrl || '#';
        const userName = button.dataset.userName || 'User';
        const userEmail = button.dataset.userEmail || '';

        form.setAttribute('action', actionUrl);
        form.reset();

        if (userNameTarget) {
            userNameTarget.textContent = userEmail && userEmail !== '-'
                ? `${userName} (${userEmail})`
                : userName;
        }

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
        form.setAttribute('action', '#');
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', () => openModal(button));
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeModal);
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

setupUserIndexStatusModal({
    modalId: 'indexSuspendUserModal',
    formId: 'indexSuspendUserForm',
    openSelector: '[data-open-user-suspend]',
    closeSelector: '[data-close-index-suspend-modal]',
    userNameSelector: '[data-index-suspend-user-name]',
});

setupUserIndexStatusModal({
    modalId: 'indexBlockUserModal',
    formId: 'indexBlockUserForm',
    openSelector: '[data-open-user-block]',
    closeSelector: '[data-close-index-block-modal]',
    userNameSelector: '[data-index-block-user-name]',
});

document.querySelectorAll('button[disabled][title]').forEach((button) => {
    button.addEventListener('click', (event) => event.preventDefault());
});
