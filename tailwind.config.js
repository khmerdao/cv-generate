/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ['./templates/**/*.html.twig', './assets/**/*.{ts,tsx}', './src/**/*.php'],
    theme: {
        extend: {
            colors: {
                // HireNova-inspired design system — docs/CV Tailor Design System (standalone).html
                accent: { DEFAULT: '#F17EB0', hover: '#D9629A', soft: '#F6A9C6' },
                ink: { DEFAULT: '#2E2A45', muted: '#5A5570', faint: '#8A83A0' },
                surface: { DEFAULT: '#FFFFFF', alt: '#E4DEF7', section: '#FBF3EE', line: '#E9E4F5' },
                pastel: {
                    lavender: '#E4DEF7',
                    violet: '#C8BCEF',
                    yellow: '#F3ED6B',
                    pink: '#F6A9C6',
                    mint: '#9FE8CB',
                },
            },
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                display: ['Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            borderRadius: { xl2: '20px' },
            boxShadow: { soft: '0 8px 24px rgba(46, 42, 69, 0.10)' },
        },
    },
    plugins: [],
};
