class DashboardCore {
    data: unknown = null;
    chart: unknown = null;
    updateInterval: number | null = null;

    async fetchData(endpoint: string): Promise<ApiResponse> {
        try {
            const response = await fetchWithTimeout(endpoint);
            return await response.json() as ApiResponse;
        } catch (error) {
            console.error('Erro ao buscar dados:', error);
            throw error;
        }
    }

    animateCounter(element: HTMLElement, start: number, end: number, duration: number = 1000): void {
        let startTimestamp: number | null = null;
        const step = (timestamp: number) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const value = Math.floor(progress * (end - start) + start);
            element.textContent = String(value);
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    }
}

window.DashboardCore = DashboardCore as unknown as typeof window.DashboardCore;
