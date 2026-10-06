/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{js,ts,jsx,tsx}"],
  theme: {
    extend: {
      colors: {
        navy: {
          950: "#0c1b33",
          900: "#12284b",
          800: "#1a3a66",
          700: "#1c3d6e",
          100: "#e7eef8",
        },
        gold: {
          500: "#e4b23c",
          600: "#c8922a",
          700: "#8a5a12",
          100: "#fff6df",
        },
        ink: "#14233c",
        muted: "#5d6e86",
        canvas: "#f6f3ec",
        line: "#e4ddd0",
      },
      fontFamily: {
        sans: ['"Noto Sans Georgian"', "Roboto", "sans-serif"],
      },
      borderRadius: {
        card: "24px",
        pill: "999px",
        sheet: "32px",
      },
      boxShadow: {
        card: "0 16px 40px rgba(18, 40, 75, 0.08)",
        float: "0 18px 40px rgba(12, 27, 51, 0.18)",
        nav: "0 12px 32px rgba(12, 27, 51, 0.16)",
      },
    },
  },
  plugins: [],
};
