/** @type {import('tailwindcss').Config} */
export default {
  // Prefix keeps Bookora utilities from colliding with WP admin / theme CSS.
  // `important: true` lets the prefixed utilities win over arbitrary front-end
  // theme styles where the public booking wizard renders.
  prefix: 'bkra-',
  important: true,
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
