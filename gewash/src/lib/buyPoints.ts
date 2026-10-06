import { customFetch } from "@/utils/customFetch";

type BuyPointsResponse = unknown;

function extractPaymentUrl(json: BuyPointsResponse): string | null {
  const obj = json as Record<string, unknown>;

  const pickStr = (v: unknown): string | null =>
    typeof v === "string" && v.trim() ? v : null;

  const data = obj["data"];
  const dataObj =
    typeof data === "object" && data !== null ? (data as Record<string, unknown>) : null;

  return (
    pickStr(obj["url"]) ??
    pickStr(obj["payment_url"]) ??
    pickStr(obj["checkout_url"]) ??
    (dataObj ? pickStr(dataObj["url"]) : null) ??
    (dataObj ? pickStr(dataObj["payment_url"]) : null) ??
    (dataObj ? pickStr(dataObj["checkout_url"]) : null) ??
    null
  );
}

/**
 * Starts points payment and redirects the browser to provider checkout.
 * `returnTo` is passed to backend so it can redirect back after payment.
 */
export async function buyPointsAndRedirect(amount: number, returnTo: string): Promise<void> {
  const token = localStorage.getItem("access_token");
  if (!token) throw new Error("Missing access token");
  if (!Number.isFinite(amount) || amount <= 0) throw new Error("Invalid amount");

  const res = await customFetch(`${import.meta.env.VITE_API_URL}/points/buy`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      amount,
      // Backend might support one of these keys; sending several helps compatibility.
      redirect_url: returnTo,
      return_url: returnTo,
      success_url: returnTo,
    }),
  });

  if (!res.ok) throw new Error(`Failed to buy points: ${res.status}`);

  const json = (await res.json()) as unknown;
  const paymentUrl = extractPaymentUrl(json);
  if (!paymentUrl) throw new Error("Payment URL not found in response");

  window.location.href = paymentUrl;
}

