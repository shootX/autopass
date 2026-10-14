import { defineConfig } from "vitest/config";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  test: {
    environment: "node",
    env: {
      VITE_API_URL: "https://api.example",
      VITE_MOCK_VOUCHER_CHECK: "false",
    },
  },
});
