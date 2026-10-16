// autopass — Tailwind colour extension
// tailwind.config.js -> theme.extend.colors = require('./tailwind-colors.js')
module.exports = {
  autopass: {
    forest: '#12284b', // Navy
    lime: '#e4b23c', // Gold
    'lime-deep': '#c8922a', // Gold pressed
    pine: '#0c1b33', // Navy 950
    moss: '#1c3d6e', // Navy 700
    mint: '#fff6df', // Gold tint
    mist: '#e7eef8', // Navy tint
    ink: '#14233c', // Ink
    foam: '#f6f3ec', // Canvas
    white: '#FFFFFF', // White
    'gray-100': '#efeae2', // Gray 100
    'gray-200': '#e4ddd0', // Gray 200
    'gray-400': '#a8b3c4', // Gray 400
    'gray-600': '#5d6e86', // Gray 600
    'gray-800': '#1a2e4a', // Gray 800
    success: '#1E9E5A', // Success
    warning: '#F0A020', // Warning
    error: '#D64541', // Error
    info: '#2E86D6', // Rinse Blue
  },
  // convenient semantic aliases
  primary: { DEFAULT: '#12284b', light: '#1c3d6e', dark: '#0c1b33' },
  accent: { DEFAULT: '#e4b23c', dark: '#c8922a' },
  success: '#1E9E5A',
  warning: '#F0A020',
  error: '#D64541',
  info: '#2E86D6',
};
