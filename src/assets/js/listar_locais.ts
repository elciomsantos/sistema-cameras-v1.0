import { initAutocomplete } from './utils/autocomplete-utils';

document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('filtro_nome') as HTMLInputElement | null;
    if (!input) return;
    const form = input.form;
    if (!form) return;

    initAutocomplete({
        inputId: 'filtro_nome',
        apiUrlBase: (window.APP_API_BASE || '') + 'api_locais',
        onSelect: (_item, _input) => {
            form.submit();
        },
    });
});
