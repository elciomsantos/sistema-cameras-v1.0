import autoprefixer from 'autoprefixer';
import cssnano from 'cssnano';
import purgecss from '@fullhuman/postcss-purgecss';

export default {
  plugins: [
    autoprefixer(),
    ...(process.env.NODE_ENV === 'production' ? [
      purgecss({
        content: [
          './src/**/*.html',
          './src/**/*.ts',
          './src/**/*.php',
          './resources/**/*.php',
          './auth/**/*.php',
          './accounts/**/*.php',
          './inc/**/*.php',
          './public/**/*.php',
        ],
        defaultExtractor: content => content.match(/[\w-/:]+(?<!:)/g) || [],
        safelist: {
          standard: [
            'show', 'collapsing', 'collapsed', 'modal-backdrop',
            'tooltip', 'popover', 'dropdown-menu', 'accordion',
            'carousel', 'carousel-item', 'fade', 'd-none',
            'leaflet-container', 'leaflet-popup',
            /^bs-/, /^navbar-/, /^nav-/, /^btn-/,
            /^alert-/, /^badge-/, /^card-/, /^form-/,
            /^table-/, /^text-/, /^bg-/, /^border-/,
            /^offset-/, /^col-/, /^row/, /^g-/,
          ],
        },
      }),
      cssnano({ preset: 'default' }),
    ] : []),
  ],
};
