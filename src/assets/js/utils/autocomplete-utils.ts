export interface AutocompleteItem {
  nome: string;
  [key: string]: unknown;
}

export interface AutocompleteOptions {
  inputId: string;
  apiUrlBase: string;
  minLength?: number;
  debounceMs?: number;
  dropdownClass?: string;
  extraParams?: () => string;
  onSelect?: (item: AutocompleteItem, input: HTMLInputElement) => void;
}

export function initAutocomplete(options: AutocompleteOptions): void {
  document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById(options.inputId) as HTMLInputElement | null;
    if (!input) return;

    const {
      minLength = 3,
      debounceMs = 300,
      dropdownClass = 'autocomplete-dropdown',
      extraParams,
      onSelect,
      apiUrlBase,
    } = options;

    const dropdown = document.createElement('div');
    dropdown.className = dropdownClass;
    dropdown.style.display = 'none';
    const wrapper = input.parentNode as HTMLElement | null;
    if (!wrapper) return;
    wrapper.style.position = 'relative';
    wrapper.appendChild(dropdown);

    input.addEventListener('input', () => {
      clearTimeout((input as unknown as Record<string, ReturnType<typeof setTimeout>>)._timer);
      const val = input.value.trim();
      if (val.length < minLength) {
        dropdown.style.display = 'none';
        return;
      }
      (input as unknown as Record<string, ReturnType<typeof setTimeout>>)._timer = setTimeout(() => {
        let url = apiUrlBase + '&q=' + encodeURIComponent(val);
        if (extraParams) url += extraParams();
        fetchWithTimeout(url)
          .then(r => r.json())
          .then(data => {
            dropdown.innerHTML = '';
            const items = data.data?.data ?? (Array.isArray(data.data) ? data.data : []);
            if (items.length > 0) {
              items.forEach((item: AutocompleteItem) => {
                const el = document.createElement('div');
                el.className = 'dropdown-item';
                el.textContent = item.nome;
                el.dataset.value = item.nome;
                el.addEventListener('click', () => {
                  input.value = el.dataset.value ?? '';
                  dropdown.style.display = 'none';
                  if (onSelect) onSelect(item, input);
                });
                dropdown.appendChild(el);
              });
              dropdown.style.display = 'block';
            } else {
              dropdown.style.display = 'none';
            }
          })
          .catch(() => { dropdown.style.display = 'none'; });
      }, debounceMs);
    });

    document.addEventListener('click', (e) => {
      if (!wrapper.contains(e.target as Node)) dropdown.style.display = 'none';
    });

    input.addEventListener('blur', () => {
      setTimeout(() => { dropdown.style.display = 'none'; }, 200);
    });
  });
}
