/** Same-origin API. Do not call back.geocar.ge. */
export function referralIpUrl(apiUrl: string): string {
  return `${apiUrl.replace(/\/$/, "")}/ref/ip`;
}
