interface LoadingManagerOptions {
    defaultTimeout?: number;
    showSpinner?: boolean;
    backdrop?: boolean;
}

interface LoadingState {
    isLoading: boolean;
    currentRequests: Map<string, { metadata: Record<string, unknown>; startTime: number }>;
    globalLoading: boolean;
    globalMessage: string;
    backdropVisible: boolean;
}

class LoadingManager {
    options: Required<LoadingManagerOptions>;
    state: LoadingState;

    constructor(options: LoadingManagerOptions = {}) {
        this.options = {
            defaultTimeout: 10000,
            showSpinner: true,
            backdrop: false,
            ...options
        };

        this.state = {
            isLoading: false,
            currentRequests: new Map(),
            globalLoading: false,
            globalMessage: '',
            backdropVisible: false
        };

        this.init();
    }

    init(): void {
        this.createGlobalLoader();
        this.setupRequestInterceptor();
    }

    createGlobalLoader(): void {
        const existing = document.getElementById('global-loader');
        if (existing) return;

        const loader = document.createElement('div');
        loader.id = 'global-loader';
        loader.className = 'global-loader';
        loader.innerHTML = [
            '<div class="global-loader-backdrop"></div>',
            '<div class="global-loader-content">',
            '<div class="spinner-border text-primary" role="status">',
            '<span class="visually-hidden">Carregando...</span>',
            '</div>',
            '<div class="global-loader-message">Carregando...</div>',
            '</div>'
        ].join('');

        document.body.appendChild(loader);
    }

    setupRequestInterceptor(): void {
        const fetchFn = window.fetchWithTimeout || window.fetch;
        const self = this;

        window.fetchWithTimeout = async function (
            this: unknown,
            ...args: Parameters<typeof fetchWithTimeout>
        ): Promise<Response> {
            const [resource, config = {}] = args;

            const requestId = self.generateRequestId(resource as RequestInfo | URL, config);

            self.startLoading(requestId, (config as any).metadata || {});

            try {
                const response = await (args.length > 2
                    ? (fetchFn as any)(args[0], args[1], args[2])
                    : (fetchFn as any)(args[0], args[1]));

                self.finishLoading(requestId);

                return response;
            } catch (error) {
                self.finishLoading(requestId);
                throw error;
            }
        } as typeof fetchWithTimeout;
    }

    generateRequestId(resource: RequestInfo | URL, config: RequestInit): string {
        const url = typeof resource === 'string' ? resource : (resource as URL).href || String(resource);
        const method = config.method || 'GET';
        const timestamp = Date.now();

        return method + '_' + url + '_' + timestamp;
    }

    startLoading(id: string, metadata: Record<string, unknown> = {}): void {
        this.state.currentRequests.set(id, {
            metadata,
            startTime: Date.now()
        });

        this.updateGlobalState();
    }

    finishLoading(id: string): void {
        this.state.currentRequests.delete(id);
        this.updateGlobalState();
    }

    updateGlobalState(): void {
        const requestCount = this.state.currentRequests.size;

        this.state.globalLoading = requestCount > 0;
        this.state.backdropVisible = requestCount > 0 && this.options.backdrop;

        const loader = document.getElementById('global-loader');
        if (loader) {
            loader.classList.toggle('show', this.state.globalLoading);
            loader.classList.toggle('backdrop', this.state.backdropVisible);

            const messageEl = loader.querySelector('.global-loader-message') as HTMLElement | null;
            if (messageEl) {
                messageEl.textContent = this.state.globalMessage || 'Carregando...';
            }
        }
    }

    show(message: string = '', options: { backdrop?: boolean; timeout?: number } = {}): void {
        this.state.globalLoading = true;
        this.state.globalMessage = message;
        this.state.backdropVisible = options.backdrop || this.options.backdrop;

        this.updateGlobalState();

        if (options.timeout) {
            setTimeout(() => {
                this.hide();
            }, options.timeout);
        }
    }

    hide(): void {
        this.state.globalLoading = false;
        this.state.globalMessage = '';
        this.state.backdropVisible = false;

        this.updateGlobalState();
    }

    withLoading<T>(promise: Promise<T>, id: string, metadata: Record<string, unknown> = {}): Promise<T> {
        this.startLoading(id, metadata);

        return promise.finally(() => {
            this.finishLoading(id);
        });
    }

    showElement(element: HTMLElement | null, message: string = ''): { hide: () => void } | void {
        if (!element) return;

        const loader = document.createElement('div');
        loader.className = 'element-loader';
        loader.innerHTML = [
            '<div class="element-loader-backdrop"></div>',
            '<div class="element-loader-content">',
            '<div class="spinner-border spinner-border-sm text-primary" role="status">',
            '<span class="visually-hidden">Carregando...</span>',
            '</div>',
            message ? '<div class="element-loader-message">' + message + '</div>' : '',
            '</div>'
        ].join('');

        element.style.position = 'relative';
        element.appendChild(loader);

        return {
            hide: () => {
                if (loader.parentNode) {
                    loader.parentNode.removeChild(loader);
                }
            }
        };
    }
}

function debounce<T extends (...args: unknown[]) => unknown>(func: T, wait: number): T & { cancel: () => void; flush: () => void } {
    let timeout: ReturnType<typeof setTimeout> | null;

    const debounced = function (this: unknown, ...args: unknown[]) {
        if (timeout) clearTimeout(timeout);
        timeout = setTimeout(() => {
            timeout = null;
            func.apply(this, args);
        }, wait);
    } as unknown as T & { cancel: () => void; flush: () => void };

    debounced.cancel = function () {
        if (timeout) {
            clearTimeout(timeout);
            timeout = null;
        }
    };

    debounced.flush = function () {
        if (timeout) {
            clearTimeout(timeout);
            timeout = null;
            func.apply(this, arguments);
        }
    };

    return debounced;
}

function throttle<T extends (...args: unknown[]) => unknown>(func: T, limit: number): (...args: unknown[]) => void {
    let inThrottle = false;
    let lastFunc: ReturnType<typeof setTimeout> | undefined;
    let lastRan: number;

    return function (this: unknown, ...args: unknown[]) {
        if (!inThrottle) {
            func.apply(this, args);
            lastRan = Date.now();
            inThrottle = true;
        } else {
            clearTimeout(lastFunc);
            lastFunc = setTimeout(function () {
                if ((Date.now() - lastRan) >= limit) {
                    func.apply(this, args);
                    lastRan = Date.now();
                }
            }, limit - (Date.now() - lastRan));
        }
    };
}

const loadingManager = new LoadingManager();
(window as any).LoadingManager = loadingManager;
(window as any).debounce = debounce;
(window as any).throttle = throttle;
