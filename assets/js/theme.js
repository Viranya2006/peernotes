// Global Theme Management System
class ThemeManager {
    constructor() {
        this.currentTheme = this.getStoredTheme() || this.getSystemTheme();
        this.init();
    }

    init() {
        this.applyTheme(this.currentTheme);
        this.createThemeToggle();
        this.watchSystemTheme();
    }

    getStoredTheme() {
        return localStorage.getItem('peernotes-theme');
    }

    setStoredTheme(theme) {
        localStorage.setItem('peernotes-theme', theme);
    }

    getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    applyTheme(theme) {
        this.currentTheme = theme;
        document.documentElement.setAttribute('data-theme', theme);
        document.body.className = document.body.className.replace(/theme-\w+/g, '');
        document.body.classList.add(`theme-${theme}`);
        
        // Update theme toggle button
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('i');
            icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        }

        // Update meta theme-color
        const metaThemeColor = document.querySelector('meta[name="theme-color"]');
        if (metaThemeColor) {
            metaThemeColor.content = theme === 'dark' ? '#0f172a' : '#ffffff';
        }

        this.setStoredTheme(theme);
        this.dispatchThemeChange(theme);
    }

    toggleTheme() {
        const newTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
        this.applyTheme(newTheme);
    }

    createThemeToggle() {
        // Remove existing toggle if any
        const existingToggle = document.getElementById('theme-toggle');
        if (existingToggle) {
            existingToggle.remove();
        }

        const toggle = document.createElement('button');
        toggle.id = 'theme-toggle';
        toggle.className = 'theme-toggle';
        toggle.setAttribute('aria-label', 'Toggle theme');
        toggle.innerHTML = `
            <i class="bi ${this.currentTheme === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill'}"></i>
        `;

        toggle.addEventListener('click', () => this.toggleTheme());
        document.body.appendChild(toggle);
    }

    watchSystemTheme() {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', (e) => {
            if (!this.getStoredTheme()) {
                this.applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    dispatchThemeChange(theme) {
        const event = new CustomEvent('themechange', { 
            detail: { theme: theme } 
        });
        document.dispatchEvent(event);
    }

    // Method to get current theme
    getCurrentTheme() {
        return this.currentTheme;
    }

    // Method to set theme programmatically
    setTheme(theme) {
        if (['light', 'dark'].includes(theme)) {
            this.applyTheme(theme);
        }
    }
}

// Initialize theme manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.themeManager = new ThemeManager();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeManager;
}