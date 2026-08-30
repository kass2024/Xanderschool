/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/renderer/**/*.{html,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        school: {
          50: '#eef6f4',
          100: '#d5ebe5',
          500: '#1b6b5a',
          700: '#0f3d34',
          900: '#08241e',
        },
      },
    },
  },
  plugins: [],
}
