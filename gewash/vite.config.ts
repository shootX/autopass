import path from "path"
import react from "@vitejs/plugin-react";
import { defineConfig, loadEnv } from "vite";
import { assertProductionBuildFlags } from "./src/lib/productionFlags.ts";

export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  assertProductionBuildFlags({
    command,
    mode,
    env,
    keys: ["VITE_BYPASS_AUTH"],
  });

  return {
    plugins: [react()],
    resolve: {
      alias: [
        { find: "@", replacement: path.resolve(__dirname, "src") }
      ],
    },
    server: {
      host: true,
      allowedHosts: true,
    },
  };
});
