const DEFAULT_TIMEOUT = 30000;

window.fetchWithTimeout = function (
    input: RequestInfo | URL,
    init?: RequestInit,
    timeout?: number
): Promise<Response> {
    const effectiveTimeout = timeout || (window as any).FETCH_TIMEOUT || DEFAULT_TIMEOUT;

    const controller = new AbortController();
    const timeoutId = setTimeout(() => {
        controller.abort();
    }, effectiveTimeout);

    const signals: AbortSignal[] = [controller.signal];
    if (init?.signal) {
        signals.push(init.signal);
    }

    const mergedInit: RequestInit = {
        ...init,
        signal: AbortSignal.any ? AbortSignal.any(signals) : controller.signal
    };

    return window.fetch(input, mergedInit).then((response: Response) => {
        clearTimeout(timeoutId);
        const csrfHeader = response.headers.get('X-CSRF-Token');
        if (csrfHeader && /^[a-f0-9]{64}$/i.test(csrfHeader)) {
            const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
            if (meta) meta.setAttribute('content', csrfHeader);
        }
        return response;
    }).catch((err: DOMException | Error) => {
        clearTimeout(timeoutId);
        if (err.name === 'AbortError') {
            throw new Error('Requisicao excedeu o tempo limite de ' + (effectiveTimeout / 1000) + 's');
        }
        throw err;
    });
};
