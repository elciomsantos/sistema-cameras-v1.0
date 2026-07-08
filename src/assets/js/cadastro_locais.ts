import { initTemAlarmeToggle, initCepInput, initBuscarCep, initGeocode } from './utils/locais-utils';

document.addEventListener('DOMContentLoaded', () => {
  const API_BASE = window.APP_API_BASE || (window.BASE_URL || '/') + 'index.php?page=api/';

  initTemAlarmeToggle();

  const cepInput = initCepInput();
  if (cepInput) {
    initBuscarCep(cepInput, API_BASE);
  }

  initGeocode(API_BASE);
});
