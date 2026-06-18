import autoprefixer from 'autoprefixer';
import cssnano from 'cssnano';

export default {
  plugins: [
    autoprefixer(),
    ...(process.env.NODE_ENV === 'production' ? [cssnano({ preset: 'default' })] : []),
  ],
};
