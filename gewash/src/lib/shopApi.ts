import { customFetch } from "@/utils/customFetch";

export type ShopTicket = {
  id: number;
  img: string;
  title: string;
  daysLeft: number;
  ticketCost: number;
  coinPrice: number;
};

export type ShopVoucher = {
  id: number;
  img: string;
  title: string;
  category: string;
  discount: string;
  coins: number;
  /** Текст с бэка для карточки детали */
  description?: string;
};

export type MyTicketEntry = {
  id: number;
  ticketId: number;
  title: string;
  img: string;
  qty: number;
};

export type MyVoucherEntry = {
  id: number;
  voucherId: number;
  title: string;
  img: string;
  code?: string;
  discount?: string;
};

/** Отображение кода как «GE - 22 - 77 - 9A»: пары символов через « - ». */
export function formatVoucherCodeDisplay(code: string): string {
  const clean = code.replace(/[\s-]/g, "").trim();
  if (!clean) return "";
  const pairs = clean.match(/.{1,2}/g) ?? [];
  return pairs.join(" - ");
}

function authHeaders(): HeadersInit {
  const token = localStorage.getItem("access_token");
  const h: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
  };
  if (token) h.Authorization = `Bearer ${token}`;
  return h;
}

/** POST /tickets/buy, /vouchers/buy — бэкенд требует токен в заголовке (как /points/buy). */
function authHeadersPurchase(): HeadersInit {
  const token = localStorage.getItem("access_token")?.trim();
  if (!token) throw new Error("Missing access token");
  return {
    Accept: "application/json",
    "Content-Type": "application/json",
    Authorization: `Bearer ${token}`,
  };
}

function num(v: unknown, fallback = 0): number {
  if (typeof v === "number" && Number.isFinite(v)) return v;
  if (typeof v === "string" && v.trim() !== "") {
    const n = Number(v);
    if (Number.isFinite(n)) return n;
  }
  return fallback;
}

function str(v: unknown, fallback = ""): string {
  return typeof v === "string" ? v : fallback;
}

/** Backend sometimes sends the literal string `"null"` for empty photo. */
function parsePhotoUrl(v: unknown): string {
  const s = str(v, "").trim();
  if (!s || s.toLowerCase() === "null") return "";
  return s;
}

function daysLeftUntilDate(dateStr: unknown): number {
  const s = str(dateStr, "").trim();
  if (!s) return 0;
  const end = new Date(s.includes("T") ? s : `${s}T23:59:59`);
  if (Number.isNaN(end.getTime())) return 0;
  const start = new Date();
  start.setHours(0, 0, 0, 0);
  const diff = end.getTime() - start.getTime();
  return Math.max(0, Math.ceil(diff / 86400000));
}

/**
 * Catalog endpoints: `{ success, tickets: { data: [...] } }` / `{ success, vouchers: { data: [...] } }`
 */
function unwrapLaravelCatalog(json: unknown, key: "tickets" | "vouchers"): unknown[] {
  if (!json || typeof json !== "object") return [];
  const root = json as Record<string, unknown>;
  const pag = root[key];
  if (!pag || typeof pag !== "object") return [];
  const data = (pag as Record<string, unknown>).data;
  return Array.isArray(data) ? data : [];
}

function unwrapList(payload: unknown): unknown[] {
  if (Array.isArray(payload)) return payload;
  if (payload && typeof payload === "object") {
    const o = payload as Record<string, unknown>;
    if (Array.isArray(o.data)) return o.data;
    for (const key of ["tickets", "vouchers"] as const) {
      const inner = o[key];
      if (inner && typeof inner === "object") {
        const d = (inner as Record<string, unknown>).data;
        if (Array.isArray(d)) return d;
      }
    }
    const inner = o.data;
    if (inner && typeof inner === "object") {
      const d = inner as Record<string, unknown>;
      if (Array.isArray(d.data)) return d.data;
    }
  }
  return [];
}

function asRecord(v: unknown): Record<string, unknown> | null {
  return v && typeof v === "object" && !Array.isArray(v) ? (v as Record<string, unknown>) : null;
}

export function normalizeTicket(raw: unknown, _index: number): ShopTicket | null {
  const r = asRecord(raw);
  if (!r) return null;
  const id = num(r.id ?? r.ticket_id);
  if (!id) return null;
  const img = parsePhotoUrl(r.photo ?? r.image_url ?? r.image ?? r.img ?? r.photo_url ?? r.thumbnail_url);
  const dateTo = r.date_to ?? r.date_end ?? r.ends_at;
  const daysLeft =
    dateTo != null && str(dateTo, "").trim() !== ""
      ? daysLeftUntilDate(dateTo)
      : num(r.days_left ?? r.days_left_to_draw ?? r.ends_in_days ?? r.days, 0);

  const countVal = num(r.count ?? r.ticket_count, 0);
  const ticketCost =
    countVal > 0
      ? countVal
      : num(r.ticket_cost ?? r.tickets ?? r.tickets_count ?? r.badge, 1);

  return {
    id,
    img,
    title: str(r.name ?? r.title ?? "Giveaway"),
    daysLeft,
    ticketCost,
    coinPrice: num(r.price ?? r.coin_price ?? r.price_in_points ?? r.points ?? r.coins),
  };
}

export function normalizeVoucher(raw: unknown, _index: number): ShopVoucher | null {
  const r = asRecord(raw);
  if (!r) return null;
  const id = num(r.id ?? r.voucher_id);
  if (!id) return null;
  const cat = asRecord(r.category);
  const categoryName = str(
    cat?.name ?? cat?.title ?? "",
  );
  const category =
    categoryName ||
    str(
      typeof r.category === "string" ? r.category : "",
      r.subtitle ?? r.type ?? "",
    );

  const percent = r.percent;
  const discount =
    typeof percent === "number" && Number.isFinite(percent)
      ? `-${percent}%`
      : str(r.discount ?? r.discount_label ?? "", "-%");

  const description = str(r.description ?? "", "").trim();

  return {
    id,
    img: parsePhotoUrl(r.photo ?? r.image_url ?? r.image ?? r.img ?? r.photo_url),
    title: str(r.name ?? r.title ?? "Voucher"),
    category,
    discount,
    coins: num(r.price ?? r.coins ?? r.price_in_points ?? r.coin_price ?? r.points),
    ...(description ? { description } : {}),
  };
}

export type ShopBundle = {
  vouchers: ShopVoucher[];
  tickets: ShopTicket[];
  myVouchers: MyVoucherEntry[];
  myTickets: MyTicketEntry[];
};

const SHOP_CACHE_KEY = "gewash_shop_v1";
let shopCache: ShopBundle | null = readShopCache();
let shopInflight: Promise<ShopBundle> | null = null;
let shopAt = 0;

function readShopCache(): ShopBundle | null {
  try {
    const raw = sessionStorage.getItem(SHOP_CACHE_KEY);
    return raw ? (JSON.parse(raw) as ShopBundle) : null;
  } catch {
    return null;
  }
}

export function peekShopBundle(): ShopBundle | null {
  return shopCache;
}

export function invalidateShopBundle() {
  shopCache = null;
  shopInflight = null;
  shopAt = 0;
  sessionStorage.removeItem(SHOP_CACHE_KEY);
}

export function loadShopBundle(): Promise<ShopBundle> {
  if (shopCache && shopAt > 0 && Date.now() - shopAt < 20000) return Promise.resolve(shopCache);
  if (shopInflight) return shopInflight;

  shopInflight = customFetch(`${import.meta.env.VITE_API_URL}/shop/home`, {
    method: "GET",
    headers: authHeaders(),
  })
    .then((res) => {
      if (!res.ok) throw new Error(`shop: ${res.status}`);
      return res.json() as Promise<Record<string, unknown>>;
    })
    .then((json) => {
      const bundle: ShopBundle = {
        vouchers: (Array.isArray(json.vouchers) ? json.vouchers : [])
          .map((row, i) => normalizeVoucher(row, i))
          .filter((x): x is ShopVoucher => x !== null),
        tickets: (Array.isArray(json.tickets) ? json.tickets : [])
          .map((row, i) => normalizeTicket(row, i))
          .filter((x): x is ShopTicket => x !== null),
        myVouchers: (Array.isArray(json.my_vouchers) ? json.my_vouchers : [])
          .map((row, i) => normalizeMyVoucher(row, i))
          .filter((x): x is MyVoucherEntry => x !== null),
        myTickets: (Array.isArray(json.my_tickets) ? json.my_tickets : [])
          .map((row, i) => normalizeMyTicket(row, i))
          .filter((x): x is MyTicketEntry => x !== null),
      };
      shopCache = bundle;
      shopAt = Date.now();
      sessionStorage.setItem(SHOP_CACHE_KEY, JSON.stringify(bundle));
      return bundle;
    })
    .finally(() => {
      shopInflight = null;
    });

  return shopInflight;
}

export async function fetchTickets(): Promise<ShopTicket[]> {
  const res = await customFetch(`${import.meta.env.VITE_API_URL}/tickets`, {
    method: "GET",
    headers: authHeaders(),
  });
  if (!res.ok) throw new Error(`tickets: ${res.status}`);
  const json = (await res.json()) as unknown;
  const list = unwrapLaravelCatalog(json, "tickets");
  return list
    .map((row, i) => normalizeTicket(row, i))
    .filter((x): x is ShopTicket => x !== null);
}

export async function fetchVouchers(): Promise<ShopVoucher[]> {
  const res = await customFetch(`${import.meta.env.VITE_API_URL}/vouchers`, {
    method: "GET",
    headers: authHeaders(),
  });
  if (!res.ok) throw new Error(`vouchers: ${res.status}`);
  const json = (await res.json()) as unknown;
  const list = unwrapLaravelCatalog(json, "vouchers");
  return list
    .map((row, i) => normalizeVoucher(row, i))
    .filter((x): x is ShopVoucher => x !== null);
}

function normalizeMyTicket(raw: unknown, index: number): MyTicketEntry | null {
  const r = asRecord(raw);
  if (!r) return null;
  const ticket = asRecord(r.ticket ?? r.giveaway);
  const ticketId = num(
    r.ticket_id ?? ticket?.id ?? r.id,
  );
  const id = num(r.id ?? r.pivot_id ?? ticketId + index * 1000);
  if (!ticketId && !id) return null;
  const title = str(
    r.title ?? ticket?.title ?? ticket?.name ?? "Ticket",
  );
  const img = str(
    r.image_url ?? ticket?.image_url ?? ticket?.image ?? "",
  );
  return {
    id: id || ticketId,
    ticketId: ticketId || id,
    title,
    img,
    qty: num(r.qty ?? r.quantity ?? r.count ?? 1, 1),
  };
}

function normalizeMyVoucher(raw: unknown, index: number): MyVoucherEntry | null {
  const r = asRecord(raw);
  if (!r) return null;
  const voucher = asRecord(r.voucher ?? r.offer);
  const catalogId = num(r.voucher_id ?? voucher?.id);
  const voucherId = catalogId > 0 ? catalogId : num(r.id);
  const id = num(r.id ?? index);
  if (!voucherId) return null;
  const title = str(r.name ?? r.title ?? voucher?.title ?? voucher?.name ?? "Voucher");
  const img = parsePhotoUrl(
    r.photo ?? r.image_url ?? voucher?.image_url ?? voucher?.image ?? "",
  );
  const code = str(r.code ?? r.voucher_code ?? r.promo_code ?? "");
  return {
    id,
    voucherId,
    title,
    img,
    code: code || undefined,
    discount: str(r.discount ?? voucher?.discount ?? "", "") || undefined,
  };
}

export async function fetchMyTickets(): Promise<MyTicketEntry[]> {
  const res = await customFetch(`${import.meta.env.VITE_API_URL}/tickets/my`, {
    method: "GET",
    headers: authHeaders(),
  });
  if (!res.ok) throw new Error(`tickets/my: ${res.status}`);
  const json = (await res.json()) as unknown;
  const list = unwrapList(json);
  return list
    .map((row, i) => normalizeMyTicket(row, i))
    .filter((x): x is MyTicketEntry => x !== null);
}

export async function fetchMyVouchers(): Promise<MyVoucherEntry[]> {
  const res = await customFetch(`${import.meta.env.VITE_API_URL}/vouchers/my`, {
    method: "GET",
    headers: authHeaders(),
  });
  if (!res.ok) throw new Error(`vouchers/my: ${res.status}`);
  const json = (await res.json()) as unknown;
  const list = unwrapList(json);
  return list
    .map((row, i) => normalizeMyVoucher(row, i))
    .filter((x): x is MyVoucherEntry => x !== null);
}

export async function buyTicket(ticketId: number, qty: number): Promise<unknown> {
  const res = await customFetch(`${import.meta.env.VITE_API_URL}/tickets/buy`, {
    method: "POST",
    headers: authHeadersPurchase(),
    body: JSON.stringify({ ticket_id: ticketId, qty }),
  });
  if (!res.ok) {
    const text = await res.text().catch(() => "");
    throw new Error(text || `tickets/buy: ${res.status}`);
  }
  invalidateShopBundle();
  try {
    return await res.json();
  } catch {
    return null;
  }
}

export async function buyVoucher(voucherId: number): Promise<unknown> {
  const res = await customFetch(`${import.meta.env.VITE_API_URL}/vouchers/buy`, {
    method: "POST",
    headers: authHeadersPurchase(),
    body: JSON.stringify({ voucher_id: voucherId }),
  });
  if (!res.ok) {
    const text = await res.text().catch(() => "");
    throw new Error(text || `vouchers/buy: ${res.status}`);
  }
  invalidateShopBundle();
  try {
    return await res.json();
  } catch {
    return null;
  }
}

function extractVoucherCode(json: unknown): string | null {
  if (!json || typeof json !== "object") return null;
  const o = json as Record<string, unknown>;
  const pick = (v: unknown) => (typeof v === "string" && v.trim() ? v : null);
  return (
    pick(o.code) ??
    pick(o.voucher_code) ??
    pick(o.promo_code) ??
    (asRecord(o.data) ? pick(asRecord(o.data)!.code) : null) ??
    (asRecord(o.data) ? pick(asRecord(o.data)!.voucher_code) : null) ??
    null
  );
}

export { extractVoucherCode };
