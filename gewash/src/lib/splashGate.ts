export const SPLASH_MAX_MS = 1500;

export function splashShouldBlock(input: {
  langLoading: boolean;
  waitingForRole: boolean;
  elapsedMs: number;
}): boolean {
  if (input.elapsedMs >= SPLASH_MAX_MS) return false;
  return input.langLoading || input.waitingForRole;
}
