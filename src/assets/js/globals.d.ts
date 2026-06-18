/// <reference types="vite/client" />

// ===== Global variable declarations (set by PHP templates) =====
declare const BASE_URL: string;
declare const APP_API_BASE: string;

// ===== API response types =====
interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  message?: string;
  error?: string;
}

interface AnexoData {
  id: string;
  url: string;
  nome_original: string;
  mime_type: string | null;
  tamanho: number;
  tamanho_formatado?: string;
}

// ===== Global bare functions (used as bare names, not window.x) =====
declare function fetchWithTimeout(input: RequestInfo | URL, init?: RequestInit, timeout?: number): Promise<Response>;
declare function showToast(message: string, type?: 'success' | 'danger' | 'warning' | 'info'): void;
declare function showConfirm(title: string, htmlContent: string, confirmText?: string): Promise<boolean>;
declare function showSuccessModal(title?: string, message?: string): void;
declare function showMessage(type: string, text: string): void;
declare function clearMessage(): void;
declare function aplicarUppercaseUniversal(formOrSelector: string | Element | null): void;
declare function loadAnexos(container: HTMLElement): void;
declare function initAnexoSection(container: HTMLElement | null): void;
declare function escapeHtml(text: unknown): string;
declare function uploadFile(container: HTMLElement, file: File, tipo: string, descricao: string, onProgress?: (pct: number) => void): Promise<AnexoData>;

// Shared maintenance utilities (bare)
declare function toBrDate(value: unknown): string;
declare function toDateTimeLocalNow(): string;
declare function toIsoDateStamp(): string;
declare function toDateOnlyBr(value: unknown): string;
declare function toTimeOnlyBr(value: unknown): string;
declare function csvEscape(value: unknown): string;
declare function renderSelectOptions(selectId: string, data: Array<Record<string, unknown>> | null | undefined, includeEmptyLabel: string | false | undefined): void;
declare function hasSelectableOptions(selectId: string): boolean;
declare function ensureSelectOption(select: HTMLSelectElement | null, value: string, label: string): void;
declare function syncHistoricoTopScroll(): void;
declare function setupHistoricoDualScroll(): void;
declare function updateSelectedCount(selectedIds: Set<string>): void;
declare function updateSelectAllCheckbox(selectedIds: Set<string>, historico: Array<Record<string, unknown>>): void;
declare function renderPaginacao(pagination: Record<string, unknown> | null | undefined, onPageChange: (page: number) => void): void;
declare function renderSortIndicators(sortBy: string, sortDir: string): void;

// ===== Window interface extensions =====
interface Window {
  APP_API_BASE?: string;
  BASE_URL?: string;
  FETCH_TIMEOUT?: number;
  CSRF_TOKEN?: () => string;

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

// ===== Chart.js =====
declare class Chart {
  constructor(ctx: CanvasRenderingContext2D | HTMLCanvasElement | string, config: Record<string, unknown>);
  destroy(): void;
  update(type?: 'resize' | 'default' | 'none'): void;
  resize(): void;
  static register(...items: unknown[]): void;
  data: Record<string, unknown>;
  options: Record<string, unknown>;
  getDatasetMeta(datasetIndex: number): { data: Array<{ x: number; y: number; options?: Record<string, unknown> }> };
  canvas: HTMLCanvasElement;
  ctx: CanvasRenderingContext2D | null;
  width: number;
  height: number;
}

// jQuery (loaded via CDN on some pages)
declare var $: any;

// Bootstrap global (loaded via importmap or CDN)
declare var bootstrap: {
  Toast: new (el: HTMLElement, opts?: Record<string, unknown>) => {
    show(): void;
    hide(): void;
  };
  Modal: (new (el: HTMLElement, opts?: Record<string, unknown>) => {
    show(): void;
    hide(): void;
  }) & {
    getOrCreateInstance(el: HTMLElement, opts?: Record<string, unknown>): { show(): void; hide(): void };
    getInstance(el: HTMLElement): { show(): void; hide(): void } | null;
  };
  Tooltip: new (el: HTMLElement, opts?: Record<string, unknown>) => {
    show(): void;
    hide(): void;
  };
  Dropdown: new (el: HTMLElement, opts?: Record<string, unknown>) => void;
};

// BootstrapModal type alias
type BootstrapModal = InstanceType<typeof bootstrap.Modal>;
