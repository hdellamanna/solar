/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.vue",
    "./resources/**/*.js",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Solar Money — Fintech Premium palette
        // Primary: violet/indigo (Nubank-inspired, but cooler)
        // Accent: warm solar gold/orange (the sun)
        // Surfaces: deep navy-black (sophisticated, not pure black)
        primary: {
          50:  '#f5f3ff',
          100: '#ede9fe',
          200: '#ddd6fe',
          300: '#c4b5fd',
          400: '#a78bfa',
          500: '#8b5cf6',
          600: '#7c3aed',
          700: '#6d28d9',
          800: '#5b21b6',
          900: '#4c1d95',
          950: '#2e1065',
        },
        solar: {
          50:  '#fffbeb',
          100: '#fef3c7',
          200: '#fde68a',
          300: '#fcd34d',
          400: '#fbbf24',
          500: '#f59e0b',
          600: '#ea580c',  // deeper solar orange
          700: '#c2410c',
          800: '#9a3412',
        },
        // Surface tokens — premium fintech dark
        ink: {
          50:  '#f8fafc',
          100: '#f1f5f9',
          200: '#e2e8f0',
          300: '#cbd5e1',
          400: '#94a3b8',
          500: '#64748b',
          600: '#475569',
          700: '#334155',
          800: '#1e293b',
          900: '#0f172a',
          950: '#0b0f1a',  // deep navy-black surface
        },
        // Semantic
        income:    '#10b981',
        expense:   '#ef4444',
        'income-soft': '#d1fae5',
        'expense-soft': '#fee2e2',
      },
      fontFamily: {
        // Distinctive but readable: Manrope for display, Inter for body, JetBrains Mono for numbers
        sans:    ['Manrope', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        display: ['Manrope', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        body:    ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        mono:    ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
      },
      fontSize: {
        'display-xl': ['3.5rem',   { lineHeight: '1.05', letterSpacing: '-0.025em', fontWeight: '700' }],
        'display-lg': ['2.75rem',  { lineHeight: '1.05', letterSpacing: '-0.025em', fontWeight: '700' }],
        'display-md': ['2.25rem',  { lineHeight: '1.1',  letterSpacing: '-0.02em',  fontWeight: '600' }],
        'display-sm': ['1.75rem',  { lineHeight: '1.15', letterSpacing: '-0.015em', fontWeight: '600' }],
      },
      backgroundImage: {
        // Premium mesh gradient — solar + violet blend (Apple Card × Revolut)
        'mesh-solar': "radial-gradient(at 20% 0%, rgba(255,138,61,0.12) 0px, transparent 50%), radial-gradient(at 80% 0%, rgba(124,58,237,0.10) 0px, transparent 50%), radial-gradient(at 0% 100%, rgba(255,138,61,0.08) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(124,58,237,0.08) 0px, transparent 50%)",
        'mesh-dark':  "radial-gradient(at 20% 0%, rgba(255,138,61,0.18) 0px, transparent 50%), radial-gradient(at 80% 0%, rgba(124,58,237,0.18) 0px, transparent 50%), radial-gradient(at 50% 100%, rgba(245,158,11,0.12) 0px, transparent 60%)",
        'hero-gradient': 'linear-gradient(135deg, #FF8A3D 0%, #FFC93C 45%, #8b5cf6 100%)',
        'card-gradient': 'linear-gradient(135deg, rgba(255,138,61,0.04) 0%, rgba(124,58,237,0.04) 100%)',
      },
      animation: {
        'sun-rotate': 'sun-rotate 60s linear infinite',
        'sun-pulse': 'sun-pulse 4s ease-in-out infinite',
        'gradient-shift': 'gradient-shift 8s ease infinite',
        'fade-up': 'fade-up 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards',
        'fade-in': 'fade-in 0.3s ease-out forwards',
        'sparkline-draw': 'sparkline-draw 1.2s ease-out forwards',
        'shimmer': 'shimmer 2.4s linear infinite',
        'mesh-drift-a': 'mesh-drift-a 24s ease-in-out infinite',
        'mesh-drift-b': 'mesh-drift-b 32s ease-in-out infinite',
        'liquid-spring-in': 'liquid-spring-in 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards',
      },
      keyframes: {
        'sun-rotate': {
          '0%': { transform: 'rotate(0deg)' },
          '100%': { transform: 'rotate(360deg)' },
        },
        'sun-pulse': {
          '0%, 100%': { opacity: '0.4', transform: 'scale(1)' },
          '50%': { opacity: '0.7', transform: 'scale(1.08)' },
        },
        'gradient-shift': {
          '0%, 100%': { 'background-position': '0% 50%' },
          '50%': { 'background-position': '100% 50%' },
        },
        'fade-up': {
          '0%': { opacity: '0', transform: 'translateY(8px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'fade-in': {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        'sparkline-draw': {
          '0%': { 'stroke-dashoffset': '100' },
          '100%': { 'stroke-dashoffset': '0' },
        },
        'shimmer': {
          '0%': { 'background-position': '-200% 0' },
          '100%': { 'background-position': '200% 0' },
        },
        'mesh-drift-a': {
          '0%, 100%': { transform: 'translate(0, 0) scale(1)' },
          '33%     ': { transform: 'translate(2%, -1%) scale(1.05)' },
          '66%     ': { transform: 'translate(-2%, 1%) scale(0.97)' },
        },
        'mesh-drift-b': {
          '0%, 100%': { transform: 'translate(0, 0) scale(1) rotate(0deg)' },
          '50%     ': { transform: 'translate(3%, 2%) scale(1.1) rotate(5deg)' },
        },
        'liquid-spring-in': {
          '0%':   { opacity: '0', transform: 'translateY(20px) scale(0.96)' },
          '60%':  { opacity: '1' },
          '100%': { opacity: '1', transform: 'translateY(0) scale(1)' },
        },
      },
      transitionTimingFunction: {
        'spring': 'cubic-bezier(0.34, 1.56, 0.64, 1)',
        'glass': 'cubic-bezier(0.25, 0.1, 0.25, 1)',
      },
      boxShadow: {
        // Premium shadows — soft, layered, never harsh
        'sm-soft': '0 1px 2px 0 rgba(15, 23, 42, 0.04)',
        'soft':    '0 2px 8px -2px rgba(15, 23, 42, 0.06), 0 1px 2px 0 rgba(15, 23, 42, 0.04)',
        'lift':    '0 12px 32px -8px rgba(15, 23, 42, 0.12), 0 4px 8px -2px rgba(15, 23, 42, 0.06)',
        'glow-violet': '0 0 0 1px rgba(124, 58, 237, 0.12), 0 8px 24px -4px rgba(124, 58, 237, 0.18)',
        'glow-solar':  '0 0 0 1px rgba(255, 138, 61, 0.15), 0 8px 24px -4px rgba(255, 138, 61, 0.22)',
        'inner-soft': 'inset 0 1px 2px 0 rgba(15, 23, 42, 0.04)',
      },
      borderRadius: {
        '3xl': '1.5rem',
        '4xl': '2rem',
        '5xl': '2.5rem',
      },
      backgroundSize: {
        '200': '200% 200%',
      },
    },
  },
  plugins: [],
}
