import { initSmoothScroll } from '../common/smooth-scroll.js';

// ===== Admin dark/light theme toggle =====
// Default theme is dark. Selected theme is persisted in localStorage
// so every admin page keeps the same theme until admin changes it again.
(function () {
    const storageKey = 'kryvex_admin_theme';

    function applyTheme(theme) {
        const normalized = theme === 'light' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-admin-theme', normalized);
        localStorage.setItem(storageKey, normalized);

        const toggle = document.getElementById('adminThemeToggle');
        if (toggle) {
            const text = toggle.querySelector('.theme-toggle-text');
            const icon = toggle.querySelector('.theme-icon');
            toggle.dataset.theme = normalized;
            if (text) text.textContent = normalized === 'light' ? 'Dark' : 'Light';
            if (icon) icon.textContent = normalized === 'light' ? '☾' : '☀';
            toggle.setAttribute('aria-label', normalized === 'light' ? 'Switch to dark theme' : 'Switch to light theme');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initSmoothScroll();
        applyTheme(localStorage.getItem(storageKey) || 'dark');

        document.getElementById('adminThemeToggle')?.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-admin-theme') || 'dark';
            applyTheme(current === 'light' ? 'dark' : 'light');
        });
    });
})();

const sidebarToggle = document.getElementById('sidebarToggle');
const adminSidebar = document.getElementById('adminSidebar');
const mobileOverlay = document.getElementById('mobileOverlay');

function closeSidebar() {
    adminSidebar?.classList.remove('open');
    mobileOverlay?.classList.remove('show');
}

sidebarToggle?.addEventListener('click', () => {
    adminSidebar?.classList.toggle('open');
    mobileOverlay?.classList.toggle('show');
});

mobileOverlay?.addEventListener('click', closeSidebar);

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeSidebar();
});

document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.getElementById('confirmModal');
    const titleEl = document.getElementById('confirmModalTitle');
    const messageEl = document.getElementById('confirmModalMessage');
    const cancelBtn = document.getElementById('confirmCancelBtn');
    const okBtn = document.getElementById('confirmOkBtn');
    const noteWrap = document.getElementById('confirmNoteWrap');
    const noteInput = document.getElementById('confirmNoteInput');
    const noteError = document.getElementById('confirmNoteError');

    let pendingForm = null;
    let pendingSubmitter = null;
    let pendingNoteInputName = null;

    const closeConfirm = () => {
        overlay?.classList.remove('show');
        overlay?.setAttribute('aria-hidden', 'true');
        pendingForm = null;
        pendingSubmitter = null;
        pendingNoteInputName = null;
        if (noteInput) noteInput.value = '';
        if (noteError) noteError.textContent = '';
        noteWrap?.classList.remove('show');
        okBtn?.classList.remove('danger');
    };

    const openConfirm = ({ form, submitter }) => {
        pendingForm = form;
        pendingSubmitter = submitter;

        const title = submitter?.dataset.confirmTitle || form.dataset.confirmTitle || 'Are you sure?';
        const message = submitter?.dataset.confirmMessage || form.dataset.confirmMessage || 'Please confirm this action.';
        const button = submitter?.dataset.confirmButton || form.dataset.confirmButton || 'Confirm';
        const danger = (submitter?.dataset.confirmDanger || form.dataset.confirmDanger || '') === 'true';
        const requiresNote = (submitter?.dataset.requiresNote || form.dataset.requiresNote || '') === 'true';

        pendingNoteInputName = submitter?.dataset.noteInput || form.dataset.noteInput || null;

        titleEl.textContent = title;
        messageEl.textContent = message;
        okBtn.textContent = button;
        okBtn.classList.toggle('danger', danger);
        noteWrap.classList.toggle('show', requiresNote);
        noteInput.required = requiresNote;
        noteInput.value = submitter?.dataset.defaultNote || form.dataset.defaultNote || '';
        noteError.textContent = '';

        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
        if (requiresNote && noteInput) {
            setTimeout(() => {
                try {
                    noteInput.focus({ preventScroll: true });
                } catch (e) {
                    noteInput.focus();
                }
            }, 80);
        }
    };

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                form.dataset.confirmed = 'false';
                return;
            }

            event.preventDefault();
            openConfirm({ form, submitter: event.submitter });
        });
    });

    cancelBtn?.addEventListener('click', closeConfirm);

    overlay?.addEventListener('click', (event) => {
        if (event.target === overlay) closeConfirm();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && overlay?.classList.contains('show')) {
            closeConfirm();
        }
    });

    okBtn?.addEventListener('click', () => {
        if (!pendingForm) return;

        if (noteWrap?.classList.contains('show')) {
            const note = noteInput.value.trim();
            if (note.length < 3) {
                noteError.textContent = 'Please enter at least 3 characters.';
                return;
            }

            if (pendingNoteInputName) {
                let hidden = pendingForm.querySelector(`[name="${pendingNoteInputName}"]`);
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = pendingNoteInputName;
                    pendingForm.appendChild(hidden);
                }
                hidden.value = note;
            }
        }

        if (pendingSubmitter?.name) {
            let hiddenSubmit = pendingForm.querySelector('input[data-confirm-submitter="true"]');
            if (!hiddenSubmit) {
                hiddenSubmit = document.createElement('input');
                hiddenSubmit.type = 'hidden';
                hiddenSubmit.dataset.confirmSubmitter = 'true';
                pendingForm.appendChild(hiddenSubmit);
            }
            hiddenSubmit.name = pendingSubmitter.name;
            hiddenSubmit.value = pendingSubmitter.value;
        }

        pendingForm.dataset.confirmed = 'true';
        pendingForm.submit();
        closeConfirm();
    });

    setupBackNavigationBlock();
});

function setupBackNavigationBlock() {
    const currentPath = window.location.pathname;
    if (currentPath === '/admin' || currentPath === '/admin/' || currentPath === '/admin/dashboard') {
        history.pushState({ noBack: true }, null, window.location.href);
        window.addEventListener('popstate', function () {
            window.close();
            setTimeout(() => {
                window.location.href = 'about:blank';
            }, 50);
        });
    }
}
