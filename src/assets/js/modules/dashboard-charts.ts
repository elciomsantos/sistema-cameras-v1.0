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
            this.charts.get(canvasId)!.destroy();
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
        const allTypes = ['FIXA', 'SPEED DOME', 'LPR', 'FACIAL'];
        const colorMap: Record<string, string> = {
            'FIXA': '#3498db',
            'SPEED DOME': '#f39c12',
            'LPR': '#9b59b6',
            'FACIAL': '#1abc9c'
        };
        const counts: Record<string, number> = { FIXA: 0, 'SPEED DOME': 0, LPR: 0, FACIAL: 0 };
        if (Array.isArray(tipoData)) {
            tipoData.forEach(item => {
                const key = String(item.tipo || '').toUpperCase();
                const qty = Number(item.quantidade || 0);
                if (counts.hasOwnProperty(key)) {
                    counts[key] += qty;
                } else {
                    allTypes.push(key);
                    colorMap[key] = '#95a5a6';
                }
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
            const canvas = document.getElementById('alarmChart');
            if (canvas) {
                canvas.parentElement!.innerHTML = '<p class="text-muted text-center py-4">Nenhum dado de alarme dispon&iacute;vel.</p>';
            }
            return;
        }
        const labels: string[] = [];
        const data: number[] = [];
        const colors: string[] = [];
        if (funcionando > 0) { labels.push('Funcionando'); data.push(funcionando); colors.push('#27ae60'); }
        if (parada > 0) { labels.push('Parada'); data.push(parada); colors.push('#e74c3c'); }
        if (semAlarme > 0) { labels.push('Sem Alarme'); data.push(semAlarme); colors.push('#95a5a6'); }
        if (data.length === 0) return;
        const canvas = document.getElementById('alarmChart');
        if (!canvas) return;
        this.createChart('alarmChart', 'doughnut', {
            labels,
            datasets: [{
                data,
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
