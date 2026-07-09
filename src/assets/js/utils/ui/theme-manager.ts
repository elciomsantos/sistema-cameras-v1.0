class ThemeManager {
    STORAGE_KEY = 'app_theme_preference';
    DARK_MODE_CLASS = 'dark-mode';

    constructor() {
        this.init();
    }

    init(): void {
        this.detectSystemPreference();
        this.setupToggle();
        this.setupListeners();
    }

    detectSystemPreference(): void {
        let savedTheme: string | null = null;
        try { savedTheme = localStorage.getItem(this.STORAGE_KEY); } catch {}
        if (savedTheme) {
            this.setTheme(savedTheme);
            return;
        }

        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            this.setTheme('dark');
        } else {
            this.setTheme('light');
        }
    }

    setTheme(theme: string): void {
        const isDark = theme === 'dark';
        const html = document.documentElement;
        const body = document.body;

        if (isDark) {
            body.classList.add(this.DARK_MODE_CLASS);
            html.setAttribute('data-theme', 'dark');
        } else {
            body.classList.remove(this.DARK_MODE_CLASS);
            html.setAttribute('data-theme', 'light');
        }

        try { localStorage.setItem(this.STORAGE_KEY, theme); } catch {}
        this.updateToggleButton(isDark);
    }

    setupToggle(): void {
        const toggle = document.getElementById('themeToggle');
        if (toggle) {
            toggle.addEventListener('click', () => {
                let currentTheme = 'light';
                try { currentTheme = localStorage.getItem(this.STORAGE_KEY) || 'light'; } catch {}
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                this.setTheme(newTheme);
            });
        }
    }

    setupListeners(): void {
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e: MediaQueryListEvent) => {
                const newTheme = e.matches ? 'dark' : 'light';
                this.setTheme(newTheme);
            });
        }
    }

    updateToggleButton(isDark: boolean): void {
        const toggle = document.getElementById('themeToggle');
        if (toggle) {
            const icon = toggle.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-moon', !isDark);
                icon.classList.toggle('fa-sun', isDark);
            }
            toggle.setAttribute('aria-label', isDark ? 'Ativar modo claro' : 'Ativar modo escuro');
            toggle.title = isDark ? 'Modo claro' : 'Modo escuro';
        }
    }

    getCurrentTheme(): string {
        try { return localStorage.getItem(this.STORAGE_KEY) || 'light'; } catch { return 'light'; }
    }
}

class SkeletonLoader {
    loaders: Map<string, { element: HTMLElement; skeleton: string }> = new Map();

    create(elementId: string, options: { lines?: number; type?: string } = {}): void {
        const element = document.getElementById(elementId);
        if (!element) {
            console.warn('Element ' + elementId + ' not found');
            return;
        }

        const { lines = 3, type = 'text' } = options;
        const skeleton = this.buildSkeleton(type, lines);

        element.innerHTML = skeleton;
        element.classList.add('skeleton-container', 'loading');
        this.loaders.set(elementId, { element, skeleton });
    }

    buildSkeleton(type: string, lines: number): string {
        switch (type) {
            case 'card':
                return [
                    '<div class="skeleton-card">',
                    '<div class="skeleton skeleton-heading"></div>',
                    Array(lines).fill(null).map(() => '<div class="skeleton skeleton-text"></div>').join(''),
                    '</div>'
                ].join('');

            case 'avatar':
                return [
                    '<div class="sk-flex-gap">',
                    '<div class="skeleton skeleton-avatar"></div>',
                    '<div class="sk-flex-1">',
                    '<div class="skeleton skeleton-heading"></div>',
                    '<div class="skeleton skeleton-text"></div>',
                    '</div>',
                    '</div>'
                ].join('');

            case 'list':
                return [
                    '<div class="skeleton-card">',
                    Array(lines).fill(null).map(() => [
                        '<div class="sk-flex-gap-mb">',
                        '<div class="skeleton sk-avatar-list"></div>',
                        '<div class="sk-flex-1">',
                        '<div class="skeleton skeleton-text sk-text-60"></div>',
                        '<div class="skeleton skeleton-text sk-text-40"></div>',
                        '</div>',
                        '</div>'
                    ].join('')).join(''),
                    '</div>'
                ].join('');

            case 'text':
            default:
                return [
                    '<div class="skeleton-card">',
                    Array(lines).fill(null).map(() => '<div class="skeleton skeleton-text"></div>').join(''),
                    '</div>'
                ].join('');
        }
    }

    remove(elementId: string): void {
        const loader = this.loaders.get(elementId);
        if (loader) {
            loader.element.innerHTML = '';
            loader.element.classList.remove('skeleton-container', 'loading');
            this.loaders.delete(elementId);
        }
    }

    async show(elementId: string, duration: number = 2000, options: { lines?: number; type?: string } = {}): Promise<void> {
        this.create(elementId, options);
        return new Promise((resolve) => {
            setTimeout(() => {
                this.remove(elementId);
                resolve();
            }, duration);
        });
    }
}

class AccessibilityHelper {
    static init(): void {
        this.setupAriaLabels();
        this.setupKeyboardNavigation();
        this.setupLiveRegions();
    }

    static setupAriaLabels(): void {
        document.querySelectorAll('[data-aria-label]').forEach(el => {
            el.setAttribute('aria-label', el.getAttribute('data-aria-label')!);
        });
    }

    static setupKeyboardNavigation(): void {
        document.addEventListener('keydown', (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                const modal = document.querySelector('.modal.show');
                if (modal) {
                    const closeBtn = modal.querySelector('[data-bs-dismiss="modal"]') as HTMLElement | null;
                    if (closeBtn) closeBtn.click();
                }
            }
        });
    }

    static setupLiveRegions(): void {
        const liveRegion = document.createElement('div');
        liveRegion.id = 'aria-live-region';
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.className = 'visually-hidden';
        document.body.appendChild(liveRegion);
    }

    static announce(message: string): void {
        const liveRegion = document.getElementById('aria-live-region');
        if (liveRegion) {
            liveRegion.textContent = message;
        }
    }
}

function initThemeFeatures(): void {
    new ThemeManager();
    new SkeletonLoader();
    AccessibilityHelper.init();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeFeatures);
} else {
    initThemeFeatures();
}
export {};