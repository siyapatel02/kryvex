/**
 * Kryvex Google Auto-Translator Engine
 * Supports 100% full-page auto-translation across the entire exchange.
 */

const LANGUAGE_CODES = {
    'English': 'en',
    'Gujarati': 'gu',
    'Hindi': 'hi',
    'Spanish': 'es',
    'French': 'fr',
    'German': 'de',
    'Chinese': 'zh-CN'
};

const CODE_TO_NAME = {
    'en': 'English',
    'gu': 'Gujarati',
    'hi': 'Hindi',
    'es': 'Spanish',
    'fr': 'French',
    'de': 'German',
    'zh-CN': 'Chinese'
};

/**
 * Global Callback for Google Translate SDK
 */
window.googleTranslateElementInit = function() {
    try {
        if (typeof google !== 'undefined' && google.translate && google.translate.TranslateElement) {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,gu,hi,es,fr,de,zh-CN',
                autoDisplay: false
            }, 'google_translate_element');
        }
    } catch (e) {
        console.warn('Google Translate Init Warning:', e);
    }
};

/**
 * Get the currently active language name
 */
export function getActiveLanguage() {
    const saved = localStorage.getItem('kryvex_language') || localStorage.getItem('kryvex_notif_language');
    if (saved && LANGUAGE_CODES[saved]) {
        return saved;
    }

    const match = document.cookie.match(/googtrans=\/en\/([^;]+)/);
    if (match && match[1] && CODE_TO_NAME[match[1]]) {
        return CODE_TO_NAME[match[1]];
    }

    return 'English';
}

/**
 * Set the googtrans cookie across root path and domain
 */
function setGoogleTranslateCookie(langCode) {
    const host = window.location.hostname;
    const path = 'path=/';

    if (langCode === 'en') {
        // Clear cookies to restore pure English
        document.cookie = `googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; ${path}`;
        document.cookie = `googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; domain=${host}; ${path}`;
        document.cookie = `googtrans=/en/en; ${path}`;
        document.cookie = `googtrans=/en/en; domain=${host}; ${path}`;
    } else {
        const val = `/en/${langCode}`;
        document.cookie = `googtrans=${val}; ${path}`;
        document.cookie = `googtrans=${val}; domain=${host}; ${path}`;

        const parts = host.split('.');
        if (parts.length > 1) {
            const rootDomain = '.' + parts.slice(-2).join('.');
            document.cookie = `googtrans=${val}; domain=${rootDomain}; ${path}`;
        }
    }
}

/**
 * Change the website language everywhere
 */
export function changeLanguage(langName) {
    if (!langName || !LANGUAGE_CODES[langName]) {
        langName = 'English';
    }

    const code = LANGUAGE_CODES[langName] || 'en';

    localStorage.setItem('kryvex_language', langName);
    localStorage.setItem('kryvex_notif_language', langName);

    setGoogleTranslateCookie(code);

    // Sync labels on page
    syncLanguageUI(langName);

    // Trigger Google select combo if present
    const combo = document.querySelector('.goog-te-combo');
    if (combo) {
        combo.value = code;
        combo.dispatchEvent(new Event('change'));
    }

    // Smooth reload to apply complete translation
    setTimeout(() => {
        window.location.reload();
    }, 200);
}

/**
 * Synchronize all language labels in the DOM
 */
export function syncLanguageUI(langName) {
    const lang = langName || getActiveLanguage();
    document.querySelectorAll('.user-display-language, #currentNotifLanguageText, #accountProfileLanguageText').forEach(el => {
        if (el) el.textContent = lang;
    });
}

// Expose on window object
if (typeof window !== 'undefined') {
    window.changeLanguage = changeLanguage;
    window.getActiveLanguage = getActiveLanguage;
    window.syncLanguageUI = syncLanguageUI;
}

// Initialize on DOM Ready
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        const current = getActiveLanguage();
        const code = LANGUAGE_CODES[current] || 'en';

        if (code !== 'en') {
            setGoogleTranslateCookie(code);
        }

        syncLanguageUI(current);
    });
}
