/** @type {import('tailwindcss').Config} */
export default {
  // Prefix keeps Bookora utilities from colliding with WP admin / theme CSS.
  prefix: 'bkra-',
  important: '#bookora-admin-root',
  content: ['./assets/src/**/*.{ts,tsx,html}'],
  theme: {
    extend: {
      colors: {
        bookora: {
          DEFAULT: '#0f766e',
          50: '#f0fdfa',
          600: '#0d9488',
          700: '#0f766e',
          900: '#134e4a',
        },
      },
    },
  },
  plugins: [],
};
