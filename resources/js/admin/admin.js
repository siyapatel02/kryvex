document.addEventListener('DOMContentLoaded', () => {
    setupAdminSidebar();
    setupAdminPasswordToggle();
});

function setupAdminSidebar() {
    const button = document.getElementById('adminMenuBtn');
    const sidebar = document.getElementById('adminSidebar');

    if (!button || !sidebar) {
        return;
    }

    button.addEventListener('click', () => {
        sidebar.classList.toggle('is-open');
    });
}

function setupAdminPasswordToggle() {
    document.querySelectorAll('[data-admin-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.closest('.admin-password-field')?.querySelector('input');

            if (!input) {
                return;
            }

            const isShown = button.getAttribute('data-show') === 'true';

            if (isShown) {
                input.type = 'password';
                button.setAttribute('data-show', 'false');
            } else {
                input.type = 'text';
                button.setAttribute('data-show', 'true');
            }

            const eyeIcon = button.querySelector('.icon-eye');
            const eyeOffIcon = button.querySelector('.icon-eye-off');

            if (eyeIcon && eyeOffIcon) {
                if (isShown) {
                    eyeIcon.style.setProperty('display', 'block', 'important');
                    eyeOffIcon.style.setProperty('display', 'none', 'important');
                } else {
                    eyeIcon.style.setProperty('display', 'none', 'important');
                    eyeOffIcon.style.setProperty('display', 'block', 'important');
                }
            }
        });
    });
}