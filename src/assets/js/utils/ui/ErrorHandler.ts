interface ErrorHandlerOptions {
    maxLogSize?: number;
    enableConsole?: boolean;
    enableAlerts?: boolean;
    customHandlers?: Record<string, (errorInfo: ErrorInfo) => void>;
}

interface ErrorInfo {
    error: unknown;
    context: string;
    severity: string;
    timestamp: string;
    stack: string | null;
    type: string;
}

class ErrorHandler {
    options: Required<ErrorHandlerOptions>;
    errorLog: ErrorInfo[];

    constructor(options: ErrorHandlerOptions = {}) {
        this.options = {
            maxLogSize: 100,
            enableConsole: true,
            enableAlerts: true,
            customHandlers: {},
            ...options
        };

        this.errorLog = [];
        this.init();
    }

    init(): void {
        window.addEventListener('error', (event: ErrorEvent) => {
            this.handleGlobalError(event.error, event.filename, event.lineno);
        });

        window.addEventListener('unhandledrejection', (event: PromiseRejectionEvent) => {
            this.handlePromiseError(event.reason);
        });

        this.createNotificationContainer();
    }

    createNotificationContainer(): void {
        const existing = document.getElementById('error-notification-container');
        if (existing) return;

        const container = document.createElement('div');
        container.id = 'error-notification-container';
        container.className = 'error-notification-container';
        container.innerHTML = '<div class="error-notification-wrapper"></div>';

        document.body.appendChild(container);
    }

    handle(error: unknown, context: string = '', severity: string = 'error'): ErrorInfo {
        const errorInfo: ErrorInfo = {
            error,
            context,
            severity,
            timestamp: new Date().toISOString(),
            stack: (error as Error)?.stack || null,
            type: (error as Error)?.constructor?.name || 'UnknownError'
        };

        this.addToLog(errorInfo);

        if (this.options.enableConsole) {
            this.logToConsole(errorInfo);
        }

        if (this.options.enableAlerts) {
            this.showNotification(errorInfo);
        }

        this.executeCustomHandler(errorInfo);

        return errorInfo;
    }

    handleGlobalError(error: Error | null, filename: string | undefined, lineno: number | undefined): void {
        const errorInfo: ErrorInfo = {
            error,
            context: 'Global error at ' + (filename || 'unknown') + ':' + (lineno || 0),
            severity: 'error',
            timestamp: new Date().toISOString(),
            stack: error?.stack || null,
            type: 'GlobalError'
        };

        this.addToLog(errorInfo);
        this.logToConsole(errorInfo);
        this.showNotification(errorInfo);
    }

    handlePromiseError(reason: unknown): void {
        const errorInfo: ErrorInfo = {
            error: reason,
            context: 'Unhandled promise rejection',
            severity: 'warning',
            timestamp: new Date().toISOString(),
            stack: (reason as Error)?.stack || null,
            type: 'PromiseRejection'
        };

        this.addToLog(errorInfo);
        this.logToConsole(errorInfo);
        this.showNotification(errorInfo);
    }

    addToLog(errorInfo: ErrorInfo): void {
        this.errorLog.unshift(errorInfo);

        if (this.errorLog.length > this.options.maxLogSize) {
            this.errorLog = this.errorLog.slice(0, this.options.maxLogSize);
        }
    }

    logToConsole(errorInfo: ErrorInfo): void {
        const { error, context, severity, timestamp } = errorInfo;
        const logMethod = severity === 'warning' ? 'warn' : 'error';
        const prefix = '[' + new Date(timestamp).toLocaleTimeString() + '] [' + severity.toUpperCase() + ']';

        console[logMethod](prefix + ' ' + context, error);

        if (errorInfo.stack) {
            console[logMethod]('Stack:', errorInfo.stack);
        }
    }

    showNotification(errorInfo: ErrorInfo): void {
        const wrapper = document.querySelector('.error-notification-wrapper');
        if (!wrapper) return;

        const notification = this.createNotificationElement(errorInfo);
        wrapper.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 10);

        setTimeout(() => {
            this.removeNotification(notification);
        }, 5000);

        const notifications = wrapper.querySelectorAll('.error-notification');
        if (notifications.length > 5) {
            for (let i = notifications.length - 1; i >= 5; i--) {
                this.removeNotification(notifications[i]);
            }
        }
    }

    createNotificationElement(errorInfo: ErrorInfo): HTMLElement {
        const { error, context, severity, timestamp } = errorInfo;

        const severityClasses: Record<string, string> = {
            error: 'bg-danger',
            warning: 'bg-warning',
            info: 'bg-info',
            success: 'bg-success'
        };

        const severityIcons: Record<string, string> = {
            error: 'fas fa-exclamation-triangle',
            warning: 'fas fa-exclamation-circle',
            info: 'fas fa-info-circle',
            success: 'fas fa-check-circle'
        };

        const notification = document.createElement('div');
        notification.className = 'error-notification ' + (severityClasses[severity] || 'bg-info');
        notification.innerHTML = [
            '<div class="error-notification-content">',
            '<div class="error-notification-header">',
            '<i class="' + (severityIcons[severity] || 'fas fa-info-circle') + ' me-2"></i>',
            '<strong>' + severity.toUpperCase() + '</strong>',
            '<button class="btn-close ms-2" type="button"></button>',
            '</div>',
            '<div class="error-notification-body">',
            '<div class="error-message">' + this.formatErrorMessage(error) + '</div>',
            '<div class="error-context text-muted small">' + context + '</div>',
            '<div class="error-timestamp text-muted small">' + new Date(timestamp).toLocaleString() + '</div>',
            '</div>',
            '</div>'
        ].join('\n');

        const closeBtn = notification.querySelector('.btn-close') as HTMLElement;
        closeBtn.addEventListener('click', () => {
            this.removeNotification(notification);
        });

        return notification;
    }

    removeNotification(notification: HTMLElement): void {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    formatErrorMessage(error: unknown): string {
        if (typeof error === 'string') {
            return error;
        }

        if ((error as Error)?.message) {
            return (error as Error).message;
        }

        return 'Ocorreu um erro desconhecido';
    }

    executeCustomHandler(errorInfo: ErrorInfo): void {
        const { type, severity } = errorInfo;
        const handlerKey = type + '_' + severity;

        if (this.options.customHandlers[handlerKey]) {
            try {
                this.options.customHandlers[handlerKey](errorInfo);
            } catch (handlerError) {
                console.error('Custom error handler failed:', handlerError);
            }
        }
    }

    addCustomHandler(type: string, handler: (errorInfo: ErrorInfo) => void): void {
        this.options.customHandlers[type] = handler;
    }

    removeCustomHandler(type: string): void {
        delete this.options.customHandlers[type];
    }

    getErrorLog(): ErrorInfo[] {
        return [...this.errorLog];
    }

    clearErrorLog(): void {
        this.errorLog = [];
    }

    handleApiError(error: unknown, endpoint: string = ''): ErrorInfo {
        return this.handle(error, 'API Error: ' + endpoint, 'error');
    }

    handleValidationError(error: unknown, field: string = ''): ErrorInfo {
        return this.handle(error, 'Validation Error: ' + field, 'warning');
    }

    handleNetworkError(error: unknown, url: string = ''): ErrorInfo {
        return this.handle(error, 'Network Error: ' + url, 'error');
    }

    handleTimeoutError(error: unknown, operation: string = ''): ErrorInfo {
        return this.handle(error, 'Timeout Error: ' + operation, 'warning');
    }

    getUserMessage(error: unknown, _context: string = ''): string {
        const errorMap: Record<string, string> = {
            'network': 'Erro de conexão. Verifique sua internet.',
            'timeout': 'A requisição demorou demais. Tente novamente.',
            'validation': 'Dados inválidos. Verifique os campos.',
            'authorization': 'Você não tem permissão para esta ação.',
            'not_found': 'Registro não encontrado.',
            'duplicate': 'Registro já existe.',
            'server_error': 'Erro interno do servidor. Tente novamente mais tarde.'
        };

        const errorType = this.getErrorType(error);
        return errorMap[errorType] || 'Ocorreu um erro inesperado.';
    }

    getErrorType(error: unknown): string {
        if (error instanceof TypeError) return 'validation';
        if (error instanceof ReferenceError) return 'validation';
        if (error instanceof SyntaxError) return 'validation';

        const errorMessage = ((error as Error)?.message || '').toLowerCase();

        if (errorMessage.includes('network') || errorMessage.includes('fetch')) {
            return 'network';
        }

        if (errorMessage.includes('timeout')) {
            return 'timeout';
        }

        if (errorMessage.includes('unauthorized') || errorMessage.includes('forbidden')) {
            return 'authorization';
        }

        if (errorMessage.includes('not found')) {
            return 'not_found';
        }

        if (errorMessage.includes('duplicate') || errorMessage.includes('already exists')) {
            return 'duplicate';
        }

        return 'server_error';
    }
}

const errorHandler = new ErrorHandler();
(window as any).ErrorHandler = errorHandler;
