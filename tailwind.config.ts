import type { Config } from "tailwindcss";

export default {
  content: ["./src/**/*.{ts,tsx}"],
  darkMode: "class",
  theme: {
    extend: {
      colors: {
        brand: {
          50: "#eefbf4",
          100: "#d6f5e4",
          200: "#a9ebc6",
          400: "#3ddc97",
          500: "#1fc985",
          600: "#12a06d",
          700: "#0d7e57",
          800: "#0a5b40",
          900: "#06392a",
        },
        ink: {
          50: "#f7f8fa",
          100: "#eef0f4",
          200: "#dde1e9",
          300: "#c2c8d4",
          400: "#8b93a7",
          500: "#69718a",
          600: "#4c5468",
          700: "#363c4c",
          800: "#232838",
          900: "#161a26",
          950: "#0d1018",
        },
      },
    },
  },
  plugins: [],
} satisfies Config;
