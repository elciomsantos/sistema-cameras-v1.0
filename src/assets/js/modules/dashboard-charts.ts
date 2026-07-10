class ChartManager {
    charts: Map<string, Chart> = new Map();
    centerTextPlugin = {
        id: 'centerText',
        beforeDraw(chart: Chart) {
            const { width, height, ctx } = chart as any;
            ctx.save();
            const meta = chart.getDatasetMeta(0);
            if (!meta || !meta.data || !meta.data.length) return;
            const center = meta.data[0] as any;
            if (!center) return;
            const cx = center.x, cy = center.y;
            const text = (chart as any).config.options.plugins?.centerText?.text || '';
            const lines = String(text).split('\n');
            const lineHeight = 14;
            const startY = cy - ((lines.length - 1) * lineHeight) / 2;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            lines.forEach((line: string, i: number) => {
                ctx.font = i === 0
                    ? `bold ${((chart as any).config.options.plugins?.centerText?.size || 18)}px 'Segoe UI', sans-serif`
                    : `${((chart as any).config.options.plugins?.centerText?.subSize || 11)}px 'Segoe UI', sans-serif`;
                ctx.fillStyle = (chart as any).config.options.plugins?.centerText?.color || '#2c3e50';
                ctx.fillText(line, cx, startY + i * lineHeight);
            });
            ctx.restore();
        }
    };

    createChart(canvasId: string, type: string, data: unknown, options: Record<string, unknown> = {}): Chart | null {
        const canvas = document.getElementById(canvasId) as HTMLCanvasElement | null;
        if (!canvas) return null;
        const ctx = canvas.getContext('2d');
        if (this.charts.has(canvasId)) {
            const existing = this.charts.get(canvasId)!;
            if (existing.canvas && existing.canvas.parentElement && document.getElementById(canvasId)) {
                const plugins = (options.plugins || {}) as Record<string, unknown>;
                const hasCenterText = !!(plugins.centerText as any)?.text;
                existing.data = data as any;
                if (hasCenterText) {
                    (existing.options as any).plugins = { ...(existing.options as any).plugins, centerText: plugins.centerText };
                }
                existing.update();
                return existing;
            }
            existing.destroy();
            this.charts.delete(canvasId);
        }
        const plugins = (options.plugins || {}) as Record<string, unknown>;
        const hasCenterText = !!(plugins.centerText as any)?.text;
        const defaultOptions: Record<string, unknown> = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        padding: 8,
                        font: { size: 10 }
                    }
                },
                ...plugins
            },
            cutout: '75%'
        };
        if (hasCenterText) {
            (defaultOptions as any).plugins = { ...(defaultOptions as any).plugins };
            (defaultOptions as any).plugins.centerText = plugins.centerText;
        }
        const chartPlugins = hasCenterText ? [this.centerTextPlugin] : [];
        const chart = new (window as any).Chart(ctx, {
            type,
            data,
            options: { ...defaultOptions, ...options },
            plugins: chartPlugins
        }) as Chart;
        this.charts.set(canvasId, chart);
        return chart;
    }

    updateCameraStatusChart(statusData: Array<Record<string, unknown>>): void {
        if (!Array.isArray(statusData) || statusData.length === 0) return;
        let total = 0, funcionando = 0, paradas = 0;
        statusData.forEach(item => {
            const qty = Number(item.quantidade || 0);
            total += qty;
            const s = String(item.status || '').toUpperCase();
            if (s === 'FUNCIONANDO') {
                funcionando += qty;
            } else {
                paradas += qty;
            }
        });
        const canvas = document.getElementById('statusChart');
        if (!canvas) return;
        this.createChart('statusChart', 'doughnut', {
            labels: ['Funcionando', 'Paradas'],
            datasets: [{
                data: [funcionando, paradas],
                backgroundColor: ['#27ae60', '#e74c3c'],
                borderWidth: 0
            }]
        }, {
            plugins: {
                legend: { display: true },
                centerText: {
                    text: 'Total\n' + String(total),
                    size: 16,
                    subSize: 10,
                    color: '#2c3e50'
                }
            }
        });
    }

    updateCameraTypeChart(tipoData: Array<Record<string, unknown>>): void {
        const allTypes: string[] = [];
        const colorMap: Record<string, string> = {};
        const counts: Record<string, number> = {};
        const defaultColors = ['#3498db', '#f39c12', '#9b59b6', '#1abc9c', '#e74c3c', '#2ecc71', '#e67e22', '#95a5a6'];
        if (Array.isArray(tipoData)) {
            tipoData.forEach((item, i) => {
                const key = String(item.tipo || '').toUpperCase();
                const qty = Number(item.quantidade || 0);
                if (!counts.hasOwnProperty(key)) {
                    counts[key] = 0;
                    allTypes.push(key);
                    colorMap[key] = defaultColors[i % defaultColors.length];
                }
                counts[key] += qty;
            });
        }
        const labels = allTypes;
        const values = labels.map(l => counts[l] || 0);
        const total = values.reduce((a: number, b: number) => a + b, 0);
        const canvas = document.getElementById('typeChart');
        if (!canvas) return;
        this.createChart('typeChart', 'doughnut', {
            labels,
            datasets: [{
                data: values,
                backgroundColor: labels.map(l => colorMap[l] || '#95a5a6'),
                borderWidth: 0
            }]
        }, {
            plugins: {
                legend: { display: true },
                centerText: {
                    text: 'Total\n' + String(total),
                    size: 16,
                    subSize: 10,
                    color: '#2c3e50'
                }
            }
        });
    }

    updateAlarmStatusChart(alarmStatusData: Array<Record<string, unknown>>): void {
        if (!Array.isArray(alarmStatusData)) return;
        let total = 0, funcionando = 0, parada = 0, semAlarme = 0;
        alarmStatusData.forEach(item => {
            const qty = Number(item.quantidade || 0);
            total += qty;
            const s = String(item.status || '').toUpperCase().trim();
            if (s === 'FUNCIONANDO' || s === '' || s === 'SEM STATUS' || s === 'NULL') {
                if (s === 'FUNCIONANDO') {
                    funcionando += qty;
                } else {
                    semAlarme += qty;
                }
            } else {
                parada += qty;
            }
        });
        if (total === 0) {
            if (this.charts.has('alarmChart')) {
                this.charts.get('alarmChart')!.destroy();
                this.charts.delete('alarmChart');
            }
            const emptyCanvas = document.getElementById('alarmChart');
            if (emptyCanvas && emptyCanvas.parentElement) {
                emptyCanvas.parentElement.innerHTML = '<p class="text-muted text-center py-4">Nenhum dado de alarme dispon&iacute;vel.</p>';
            }
            return;
        }
        let canvas = document.getElementById('alarmChart');
        if (!canvas) {
            const placeholders = document.querySelectorAll('.alarm-chart-placeholder');
            if (placeholders.length > 0) {
                const lastPlaceholder = placeholders[placeholders.length - 1];
                lastPlaceholder.insertAdjacentHTML('beforeend', '<canvas id="alarmChart"></canvas>');
                canvas = document.getElementById('alarmChart');
            }
        }
        if (!canvas) return;
        const labels: string[] = [];
        const dataValues: number[] = [];
        const colors: string[] = [];
        if (funcionando > 0) { labels.push('Funcionando'); dataValues.push(funcionando); colors.push('#27ae60'); }
        if (parada > 0) { labels.push('Parada'); dataValues.push(parada); colors.push('#e74c3c'); }
        if (semAlarme > 0) { labels.push('Sem Alarme'); dataValues.push(semAlarme); colors.push('#95a5a6'); }
        if (dataValues.length === 0) return;
        this.createChart('alarmChart', 'doughnut', {
            labels,
            datasets: [{
                data: dataValues,
                backgroundColor: colors,
                borderWidth: 0
            }]
        }, {
            plugins: {
                legend: { display: true },
                centerText: {
                    text: 'Total\n' + String(total),
                    size: 16,
                    subSize: 10,
                    color: '#2c3e50'
                }
            }
        });
    }
}

(window as any).ChartManager = ChartManager;
export {};