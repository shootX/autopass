/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{js,ts,jsx,tsx,html}"],
  theme: {
    extend: {
      colors: {
        autopass: {
          forest: "#14482F",
          lime: "#B5DD3A",
          "lime-deep": "#8DB523",
          pine: "#0B2A1B",
          moss: "#2D7F50",
          mint: "#DCEFD0",
          mist: "#EEF6E8",
          ink: "#0E1712",
          foam: "#F7F9F4",
          white: "#FFFFFF",
          "gray-100": "#EDF0EC",
          "gray-200": "#DCE1DB",
          "gray-400": "#A2ABA4",
          "gray-600": "#5E6A62",
          "gray-800": "#2B342E",
          success: "#1E9E5A",
          warning: "#F0A020",
          error: "#D64541",
          info: "#2E86D6",
        },
        primary: { DEFAULT: "#14482F", light: "#2D7F50", dark: "#0B2A1B" },
        accent: { DEFAULT: "#B5DD3A", dark: "#8DB523" },
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', '"Noto Sans Georgian"', "sans-serif"],
      },
    },
  },
  plugins: [],
};
