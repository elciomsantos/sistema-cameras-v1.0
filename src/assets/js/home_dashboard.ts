class HomeDashboard extends (window.DashboardCore as any) {
    chartManager: any;
    _loading = false;

    constructor() {
        super();
        this.chartManager = new (window as any).ChartManager();
        this.init();
    }

    getApiBase() {
        return window.APP_API_BASE;
    }

    async init() {
        try {
            await this.loadData();
            this.setupAutoRefresh();
            this.setupEventListeners();
            this.bootstrapTooltips();
        } catch (error) {
            console.error('Erro na inicializacao do HomeDashboard:', error);
            this.setupEventListeners();
        }
    }

    setLoading(loading: boolean) {
        const cards = document.querySelectorAll('.stat-card');
        cards.forEach(card => {
            card.classList.toggle('is-loading', loading);
        });
    }

    async loadData() {
        if (this._loading) return;
        this._loading = true;
        try {
            this.setLoading(true);
            const endpoint = `${this.getApiBase()}api_dashboard`;
            const payload = await this.fetchData(endpoint) as ApiResponse<Record<string, unknown>>;
            this.data = payload && payload.success ? payload : null;
            this.updateUI();
        } catch (error) {
            console.error('Falha ao carregar dados:', error);
            if (typeof window.showToast === 'function') {
                window.showToast('Falha ao carregar dados do dashboard. Tentando novamente...', 'warning');
            }
        } finally {
            this._loading = false;
            this.setLoading(false);
        }
    }

    updateUI() {
        if (!this.data) return;
        const data = (this.data as any).data || this.data;
        const stats = data.stats || {};

        const totalEl = document.getElementById('total-cameras');
        if (totalEl && stats.total !== undefined) {
            this.animateCounter(totalEl, 0, Number(stats.total) || 0, 1000);
        }

        const activeEl = document.getElementById('active-cameras');
        if (activeEl && stats.ativas !== undefined) {
            this.animateCounter(activeEl, 0, Number(stats.ativas) || 0, 1000);
        }

        const manutencaoEl = document.getElementById('manutencao-cameras');
        if (manutencaoEl && stats.manutencao !== undefined) {
            this.animateCounter(manutencaoEl, 0, Number(stats.manutencao) || 0, 1000);
        }

        const desativadasEl = document.getElementById('desativadas-cameras');
        if (desativadasEl && stats.desativadas !== undefined) {
            this.animateCounter(desativadasEl, 0, Number(stats.desativadas) || 0, 1000);
        }

        const alertsEl = document.getElementById('alerts-count');
        if (alertsEl && stats.atrasada !== undefined) {
            this.animateCounter(alertsEl, 0, Number(stats.atrasada) || 0, 1000);
        }

        const uptime = Number(stats.uptime) || 0;
        const uptimeEl = document.getElementById('uptime-percentual');
        if (uptimeEl) uptimeEl.textContent = uptime + '%';

        const progressLabel = document.getElementById('progress-label');
        if (progressLabel) progressLabel.textContent = uptime + '%';

        const progressBar = document.getElementById('progress-bar') as HTMLElement | null;
        if (progressBar) {
            progressBar.style.width = uptime + '%';
            progressBar.setAttribute('aria-valuenow', String(uptime));
        }

        const progressCameras = document.getElementById('progress-cameras');
        if (progressCameras) progressCameras.textContent = String(Number(stats.ativas) || 0);
        const progressTotal = document.getElementById('progress-total');
        if (progressTotal) progressTotal.textContent = String(Number(stats.total) || 0);

        const statusAtivas = document.getElementById('status-ativas');
        if (statusAtivas) statusAtivas.textContent = String(Number(stats.ativas) || 0);
        const statusManutencao = document.getElementById('status-manutencao');
        if (statusManutencao) statusManutencao.textContent = String(Number(stats.manutencao) || 0);
        const statusDesativadas = document.getElementById('status-desativadas');
        if (statusDesativadas) statusDesativadas.textContent = String(Number(stats.desativadas) || 0);

        const alertaAtraso = document.getElementById('alertaAtraso');
        if (alertaAtraso) {
            if (Number(stats.atrasada) > 0) {
                alertaAtraso.classList.remove('d-none');
                const countEl = document.getElementById('alertaAtrasoCount');
                if (countEl) countEl.textContent = String(stats.atrasada);
            } else {
                alertaAtraso.classList.add('d-none');
            }
        }

        if (this.chartManager && data.status_data) {
            this.chartManager.updateCameraStatusChart(data.status_data);
        }

        if (this.chartManager && data.camera_tipo_data) {
            this.chartManager.updateCameraTypeChart(data.camera_tipo_data);
        }

        if (this.chartManager && data.alarm_status_data) {
            this.chartManager.updateAlarmStatusChart(data.alarm_status_data);
        }

        this.renderRecentActivities(data.manutencoes);
    }

    renderRecentActivities(manutencoes: Array<Record<string, unknown>> | null | undefined) {
        const container = document.getElementById('recent-activities-body');
        if (!container) return;

        if (!manutencoes || manutencoes.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-clock fa-3x mb-3"></i>
                    <h5>Nenhuma manuten&ccedil;&atilde;o recente encontrada</h5>
                    <p>Registre manuten&ccedil;&otilde;es para ver as atualiza&ccedil;&otilde;es aqui.</p>
                </div>`;
            return;
        }

        const rows = manutencoes.map((m: Record<string, unknown>) => {
            const data = m.data_hora ? new Date((m.data_hora as string) + ' UTC').toLocaleString('pt-BR') : '-';
            const nome = (m.camera_nome as string) || 'ID ' + (m.equipamento_id || '-');
            const desc = m.descricao ? ((m.descricao as string).length > 100 ? (m.descricao as string).substring(0, 100) + '...' : m.descricao as string) : '-';
            return `<tr>
                <td>${this.escapeHtml(data)}</td>
                <td>${this.escapeHtml(nome)}</td>
                <td>${this.escapeHtml(m.status || '-')}</td>
                <td>${this.escapeHtml(m.serie_mac || '-')}</td>
                <td>${this.escapeHtml(m.tecnico_responsavel || '-')}</td>
                <td>${this.escapeHtml(desc)}</td>
            </tr>`;
        }).join('');

        container.innerHTML = `
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Equipamento</th>
                            <th>Status</th>
                            <th>S&eacute;rie/MAC</th>
                            <th>T&eacute;cnico</th>
                            <th>Resumo</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    }

    escapeHtml(str: unknown) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    bootstrapTooltips() {
        if ((window as any)._homeTooltips) {
            (window as any)._homeTooltips.forEach((t: any) => t.dispose());
        }
        const triggers = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        (window as any)._homeTooltips = Array.from(triggers).map((el: Element) => new (window as any).bootstrap.Tooltip(el));
    }

    setupAutoRefresh() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval as number);
        }
        this.updateInterval = window.setInterval(() => this.loadData(), 30000);
    }

    setupEventListeners() {
        document.addEventListener('click', (e: MouseEvent) => {
            const target = e.target as HTMLElement;
            if (target.matches('#logout') || target.closest('#logout')) {
                e.preventDefault();
                this.logout();
            }

            if (target.matches('#refresh-dashboard') || target.closest('#refresh-dashboard')) {
                e.preventDefault();
                this.loadData();
            }

            if (target.matches('#settings') || target.closest('#settings')) {
                e.preventDefault();
                window.location.href = (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + '/index.php?page=settings' as string;
            }
        });

        const menuToggle = document.getElementById('menu-toggle');
        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                document.body.classList.toggle('sidebar-collapsed');
            });
        }
    }

    logout() {
        const base = (typeof BASE_URL !== 'undefined' ? BASE_URL : '/').replace(/\/?$/, '/');
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${base}index.php?page=logout`;
        form.style.display = 'none';

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = 'csrf_token';
        csrf.value = (window as any).CSRF_TOKEN || '';
        form.appendChild(csrf);

        document.body.appendChild(form);
        form.submit();
    }

    destroy() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval as number);
        }
        if ((window as any)._homeTooltips) {
            (window as any)._homeTooltips.forEach((t: any) => t.dispose());
            (window as any)._homeTooltips = null;
        }
    }
}

function initDashboard() {
    document.body.classList.add('page-home');
    try {
        (window as any).homeDashboard = new HomeDashboard();
    } catch (e) {
        console.error('Falha ao instanciar HomeDashboard', e);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboard);
} else {
    initDashboard();
}

window.addEventListener('beforeunload', () => {
    const hd = (window as any).homeDashboard;
    if (hd && typeof hd.destroy === 'function') {
        hd.destroy();
    }
});
export {};
