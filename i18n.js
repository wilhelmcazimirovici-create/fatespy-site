import { translations } from './translations.js';

class I18nManager {
    constructor() {
        this.currentLang = 'en';
        this.init();
    }

    init() {
        const langToggleBtn = document.getElementById('lang-toggle');
        const langMenu = document.getElementById('lang-menu');
        const currentLangEl = document.getElementById('current-lang');

        if (!langToggleBtn || !langMenu) return;

        // Toggle dropdown
        langToggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            langMenu.classList.toggle('show');
        });

        // Close on outside click
        document.addEventListener('click', () => {
            langMenu.classList.remove('show');
        });

        // Change language
        langMenu.addEventListener('click', (e) => {
            if (e.target.tagName === 'BUTTON') {
                const langCode = e.target.dataset.lang;
                if (langCode && translations[langCode]) {
                    this.setLanguage(langCode);
                    currentLangEl.textContent = langCode.toUpperCase();
                }
            }
        });

        // Apply init language
        this.applyTranslations();
    }

    setLanguage(langCode) {
        this.currentLang = langCode;
        document.documentElement.lang = langCode;
        this.applyTranslations();
    }

    applyTranslations() {
        const dict = translations[this.currentLang];
        if (!dict) return;

        // Update innerText
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.dataset.i18n;
            if (dict[key]) {
                el.textContent = dict[key];
            }
        });

        // Update placeholders
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const key = el.dataset.i18nPlaceholder;
            if (dict[key]) {
                el.placeholder = dict[key];
            }
        });

        // Update RTL if Arabic
        if (this.currentLang === 'ar') {
            document.body.dir = 'rtl';
            document.body.style.fontFamily = "'Amiri', 'Inter', sans-serif";
        } else {
            document.body.dir = 'ltr';
            document.body.style.fontFamily = "'Inter', system-ui, sans-serif";
        }
    }

    updateElement(el, key) {
        const dict = translations[this.currentLang];
        if (dict && dict[key]) {
            el.textContent = dict[key];
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const manager = new I18nManager();
    window.getTranslations = () => translations[manager.currentLang] || translations['en'];
    window.updateI18nElement = (el, key) => manager.updateElement(el, key);
    window.updateHoroscopeText = (zodiacValue) => {
        const dict = window.getTranslations();
        const selectedKey = 'zodiac_' + zodiacValue;
        let defaultTxt = "Horoscope for " + (dict[selectedKey] || zodiacValue) + " updated.";
        if (typeof generateOmniReading === 'function' && window.appState && window.appState.isLoggedIn) {
            defaultTxt = generateOmniReading('Daily Horoscope', defaultTxt);
        } else if (typeof generateOmniReading === 'function' && typeof appState !== 'undefined' && appState.isLoggedIn) {
            // fallback if appState is not on window but still scoped globally
            defaultTxt = generateOmniReading('Daily Horoscope', defaultTxt);
        }
        const widgetTextEl = document.getElementById('text-horoscope-daily');
        if (widgetTextEl) widgetTextEl.innerHTML = defaultTxt;
    };
});
