window.setKryvexTheme = function(themeName) {
    const theme = themeName === "light" ? "light" : "dark";
    localStorage.setItem("kryvex_theme", theme);

    if (theme === "light") {
        document.documentElement.setAttribute("data-theme", "light");
        document.documentElement.classList.add("theme-light");
    } else {
        document.documentElement.setAttribute("data-theme", "dark");
        document.documentElement.classList.remove("theme-light");
    }

    window.updateThemeUIState();
    window.dispatchEvent(new CustomEvent("kryvexThemeChanged", { detail: { theme: theme } }));
};

window.toggleKryvexTheme = function() {
    const currentTheme = localStorage.getItem("kryvex_theme") || "dark";
    const nextTheme = currentTheme === "light" ? "dark" : "light";
    window.setKryvexTheme(nextTheme);
};

window.updateThemeUIState = function() {
    const currentTheme = localStorage.getItem("kryvex_theme") || "dark";
    const isLight = currentTheme === "light";

    // Update navbar theme toggle buttons
    const toggleBtns = document.querySelectorAll(".kv-theme-toggle-btn");
    toggleBtns.forEach((btn) => {
        const sunIcon = btn.querySelector(".theme-icon-sun");
        const moonIcon = btn.querySelector(".theme-icon-moon");
        
        if (isLight) {
            // Currently light: show moon to switch to dark
            if (sunIcon) sunIcon.hidden = true;
            if (moonIcon) moonIcon.hidden = false;
            btn.setAttribute("title", "Switch to Dark Theme");
            btn.setAttribute("aria-label", "Switch to Dark Theme");
        } else {
            // Currently dark: show sun to switch to light
            if (sunIcon) sunIcon.hidden = false;
            if (moonIcon) moonIcon.hidden = true;
            btn.setAttribute("title", "Switch to Light Theme");
            btn.setAttribute("aria-label", "Switch to Light Theme");
        }
    });

    // Also update settings page theme toggles if present
    const statusLabel = document.getElementById("themeStatusLabel");
    const btnLight = document.getElementById("btnThemeLight");
    const btnDark = document.getElementById("btnThemeDark");

    if (statusLabel) {
        statusLabel.textContent = isLight ? "Light" : "Dark";
    }

    if (btnLight && btnDark) {
        if (isLight) {
            btnLight.style.background = "#7c3aed";
            btnLight.style.color = "#ffffff";
            btnDark.style.background = "none";
            btnDark.style.color = "inherit";
        } else {
            btnDark.style.background = "#7c3aed";
            btnDark.style.color = "#ffffff";
            btnLight.style.background = "none";
            btnLight.style.color = "inherit";
        }
    }

    // Update profile dropdown theme pills
    document.querySelectorAll(".kv-theme-pill-btn").forEach((pill) => {
        const mode = pill.getAttribute("data-theme-mode");
        if (mode === currentTheme) {
            pill.classList.add("is-active");
        } else {
            pill.classList.remove("is-active");
        }
    });
};

document.addEventListener("DOMContentLoaded", () => {
    window.updateThemeUIState();
});
