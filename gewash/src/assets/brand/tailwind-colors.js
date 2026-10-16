// autopass — Tailwind colour extension
// tailwind.config.js -> theme.extend.colors = require('./tailwind-colors.js')
module.exports = {
  autopass: {
    forest: '#14482F', // Forest
    lime: '#B5DD3A', // Pass Lime
    'lime-deep': '#8DB523', // Lime Deep
    pine: '#0B2A1B', // Pine
    moss: '#2D7F50', // Moss
    mint: '#DCEFD0', // Mint
    mist: '#EEF6E8', // Mist
    ink: '#0E1712', // Ink
    foam: '#F7F9F4', // Foam
    white: '#FFFFFF', // White
    'gray-100': '#EDF0EC', // Gray 100
    'gray-200': '#DCE1DB', // Gray 200
    'gray-400': '#A2ABA4', // Gray 400
    'gray-600': '#5E6A62', // Gray 600
    'gray-800': '#2B342E', // Gray 800
    success: '#1E9E5A', // Success
    warning: '#F0A020', // Warning
    error: '#D64541', // Error
    info: '#2E86D6', // Rinse Blue
  },
  // convenient semantic aliases
  primary: { DEFAULT: '#14482F', light: '#2D7F50', dark: '#0B2A1B' },
  accent: { DEFAULT: '#B5DD3A', dark: '#8DB523' },
  success: '#1E9E5A',
  warning: '#F0A020',
  error: '#D64541',
  info: '#2E86D6',
};
