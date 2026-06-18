/// <reference types="vite/client" />

interface Window {
  APP_API_BASE?: string;
  BASE_URL?: string;
  FETCH_TIMEOUT?: number;

  // Core
  DashboardCore: typeof DashboardCore;
  ChartManager: typeof ChartManager;
  ErrorHandler: ErrorHandler;
  LoadingManager: LoadingManager;

  // Utils
  fetchWithTimeout: (input: RequestInfo | URL, init?: RequestInit, timeout?: number) => Promise<Response>;
  showToast: (message: string, type?: 'success' | 'danger' | 'warning' | 'info') => void;
  showConfirm: (title: string, htmlContent: string, confirmText?: string) => Promise<boolean>;
  initAnexoSection: (container: HTMLElement | null) => void;
  loadAnexos: (container: HTMLElement) => void;
  debounce: <T extends (...args: unknown[]) => unknown>(func: T, wait: number) => T & { cancel: () => void; flush: () => void };
  throttle: <T extends (...args: unknown[]) => unknown>(func: T, limit: number) => (...args: unknown[]) => void;

  showSuccessModal: (title?: string, message?: string) => void;

  // Shared maintenance utilities
  toBrDate: (value: unknown) => string;
  toDateTimeLocalNow: () => string;
  toIsoDateStamp: () => string;
  toDateOnlyBr: (value: unknown) => string;
  toTimeOnlyBr: (value: unknown) => string;
  escapeHtml: (value: unknown) => string;
  csvEscape: (value: unknown) => string;
  showMessage: (type: string, text: string) => void;
  clearMessage: () => void;
  renderSelectOptions: (selectId: string, data: Array<Record<string, unknown>> | null | undefined, includeEmptyLabel: string | false | undefined) => void;
  hasSelectableOptions: (selectId: string) => boolean;
  ensureSelectOption: (select: HTMLSelectElement | null, value: string, label: string) => void;
  syncHistoricoTopScroll: () => void;
  setupHistoricoDualScroll: () => void;
  updateSelectedCount: (selectedIds: Set<string>) => void;
  updateSelectAllCheckbox: (selectedIds: Set<string>, historico: Array<Record<string, unknown>>) => void;
  renderPaginacao: (pagination: Record<string, unknown> | null | undefined, onPageChange: (page: number) => void) => void;
  renderSortIndicators: (sortBy: string, sortDir: string) => void;

  // Page-specific globals
  homeDashboard?: HomeDashboardSyntax;
  aplicarUppercaseUniversal?: (formOrSelector: string | Element | null) => void;
  initThemeFeatures?: () => void;
}

interface HomeDashboardSyntax {
  init: () => void;
}

// jQuery (loaded via CDN on some pages)
declare var $: any;

// Bootstrap global (loaded via importmap or CDN)
declare var bootstrap: {
  Toast: new (el: HTMLElement, opts?: Record<string, unknown>) => {
    show(): void;
    hide(): void;
  };
  Modal: new (el: HTMLElement, opts?: Record<string, unknown>) => {
    show(): void;
    hide(): void;
  };
  Tooltip: new (el: HTMLElement, opts?: Record<string, unknown>) => {
    show(): void;
    hide(): void;
  };
  Dropdown: new (el: HTMLElement, opts?: Record<string, unknown>) => void;
};
