type Stoppable = {
  stop: () => Promise<void>;
  clear: () => Promise<void>;
};

/** Stop the camera and clear the preview. Safe if start() never finished. */
export async function releaseScanner(scanner: Stoppable | null): Promise<void> {
  if (!scanner) return;
  try {
    await scanner.stop();
  } catch {
    // Camera was not running.
  }
  try {
    await scanner.clear();
  } catch {
    // Preview was already removed.
  }
}
