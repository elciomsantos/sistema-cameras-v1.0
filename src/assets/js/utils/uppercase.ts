function aplicarUppercaseUniversal(formOrSelector: string | Element | null): void {
    const form = typeof formOrSelector === 'string'
        ? document.querySelector(formOrSelector)
        : formOrSelector;
    if (!form) return;

    const selectors = 'input[type="text"], textarea, input:not([type])';

    form.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>(selectors).forEach(field => {
        if (field.dataset.uppercase === 'false') return;

        field.addEventListener('input', function (this: HTMLInputElement | HTMLTextAreaElement) {
            const pos = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(pos, pos);
        });
    });
}
