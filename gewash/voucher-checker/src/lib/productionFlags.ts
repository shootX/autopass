export type FlagEnv = {
  PROD?: boolean | string;
  VITE_MOCK_VOUCHER_CHECK?: string;
};

const TRUTHY = new Set(["true", "1", "yes"]);

function truthy(value: string | undefined): boolean {
  if (!value) return false;
  return TRUTHY.has(value.trim().toLowerCase());
}

function production(env: FlagEnv): boolean {
  return env.PROD === true || env.PROD === "true";
}

/** Partner voucher mock never runs in a production build. */
export function voucherMockEnabled(env: FlagEnv): boolean {
  if (production(env)) return false;
  return truthy(env.VITE_MOCK_VOUCHER_CHECK);
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
