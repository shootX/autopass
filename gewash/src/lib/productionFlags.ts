export type FlagEnv = {
  PROD?: boolean;
  VITE_BYPASS_AUTH?: string;
  VITE_TEST_PAYMENTS?: string;
};

const TRUTHY = new Set(["true", "1", "yes"]);

function truthy(value: string | undefined): boolean {
  if (!value) return false;
  return TRUTHY.has(value.trim().toLowerCase());
}

/** Preview auth bypass is dev-only. A production build never honors the flag. */
export function authBypassEnabled(env: FlagEnv): boolean {
  if (env.PROD) return false;
  return env.VITE_BYPASS_AUTH === "true";
}

/** SPA sandbox screen exists only when the build is explicitly configured for test payments. */
export function sandboxRouteEnabled(env: FlagEnv): boolean {
  return env.VITE_TEST_PAYMENTS === "true";
}

export function assertProductionBuildFlags(options: {
  command: string;
  mode: string;
  env: Record<string, string | undefined>;
  keys: readonly string[];
}): void {
  if (options.command !== "build" || options.mode !== "production") return;
  for (const key of options.keys) {
    if (truthy(options.env[key])) {
      throw new Error(`${key} cannot be enabled in a production build`);
    }
  }
}
