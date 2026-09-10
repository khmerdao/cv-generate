/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ['./templates/**/*.html.twig', './assets/**/*.{ts,tsx}', './src/**/*.php'],
    theme: {
        extend: {
            colors: {
                accent: { DEFAULT: '#2563eb', hover: '#1d4ed8', soft: '#dbeafe' },
                ink: { DEFAULT: '#111827', muted: '#6b7280', faint: '#9ca3af' },
                surface: { DEFAULT: '#ffffff', alt: '#f9fafb', line: '#e5e7eb' },
            },
            fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
        },
    },
    plugins: [],
};
