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

    const mergedInit: RequestInit = {
        ...init,
    };

    if (init?.signal) {
        if (typeof AbortSignal.any === 'function') {
            mergedInit.signal = AbortSignal.any([controller.signal, init.signal]);
        } else {
            const parentSignal = init.signal;
            const onAbort = () => { if (!controller.signal.aborted) controller.abort(); };
            if (!parentSignal.aborted) {
                parentSignal.addEventListener('abort', onAbort, { once: true });
            } else {
                controller.abort();
            }
            mergedInit.signal = controller.signal;
        }
    } else {
        mergedInit.signal = controller.signal;
    }

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
