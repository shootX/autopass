const REDIRECT_PARAMS = ["redirect", "next", "return", "return_to", "returnto", "url", "to"] as const;
const SAME_ORIGIN_CHECKOUT = new Set(["/payment/sandbox", "/payment-success", "/payment/return"]);

/** Query redirects are never followed. */
export function queryRedirectPath(search: string): null {
  const params = new URLSearchParams(search.startsWith("?") ? search.slice(1) : search);
  for (const key of REDIRECT_PARAMS) {
    if (params.get(key)) return null;
  }
  return null;
}

export function safeCheckoutUrl(raw: string, pageOrigin: string): string | null {
  const trimmed = raw.trim();
  if (!trimmed || trimmed.startsWith("//") || trimmed.includes("\\")) return null;
  let url: URL;
  try {
    url = new URL(trimmed, pageOrigin);
  } catch {
    return null;
  }
  if (url.protocol !== "https:" && url.protocol !== "http:") return null;
  if (url.username || url.password) return null;
  if (url.origin === pageOrigin) {
    return SAME_ORIGIN_CHECKOUT.has(url.pathname) ? url.toString() : null;
  }
  if (url.protocol !== "https:") return null;
  return url.toString();
}

export function referralShareHref(network: "telegram" | "whatsapp" | "viber" | "facebook", link: string): string {
  const text = encodeURIComponent(link);
  switch (network) {
    case "telegram":
      return `https://t.me/share/url?url=${text}`;
    case "whatsapp":
      return `https://wa.me/?text=${text}`;
    case "viber":
      return `viber://forward?text=${text}`;
    case "facebook":
      return `https://www.facebook.com/sharer/sharer.php?u=${text}`;
  }
}
