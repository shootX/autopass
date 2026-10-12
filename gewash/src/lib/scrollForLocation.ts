export function scrollForLocation(hash: string): void {
  if (hash) return;
  window.scrollTo(0, 0);
}

export function scrollToHash(hash: string): void {
  const id = hash.replace(/^#/, "");
  if (!id) return;
  document.getElementById(id)?.scrollIntoView({ block: "start" });
}
