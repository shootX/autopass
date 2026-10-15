import { loadEnv } from "vite";
import { defineConfig } from "vitest/config";
import react from "@vitejs/plugin-react";
import { assertProductionBuildFlags } from "./src/lib/productionFlags.ts";

export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  assertProductionBuildFlags({
    command,
    mode,
    env,
    keys: ["VITE_MOCK_VOUCHER_CHECK"],
  });

  return {
    plugins: [react()],
    test: {
      environment: "node",
      env: {
        VITE_API_URL: "https://api.example",
        VITE_MOCK_VOUCHER_CHECK: "false",
      },
    },
  };
});
