export const CAR_SUV = "/v4/car-suv.webp";
export const CAR_SEDAN = "/v4/car-sedan.webp";
export const CAR_TOP = "/v4/car-top.webp";
export const MAP_V4 = "/v4/map.webp";

const BRANCH_FALLBACK = ["/v4/branch-1.webp", "/v4/branch-2.webp", "/v4/branch-3.webp"];

const SUV_TYPES = new Set(["suv", "jeep", "crossover", "pickup", "wagon", "van", "minivan"]);

const BODY_KA: Record<string, string> = {
  suv: "ჯიპი",
  jeep: "ჯიპი",
  crossover: "ჯიპი",
  sedan: "სედანი",
  hatchback: "ჰეჩბეკი",
  coupe: "კუპე",
  wagon: "უნივერსალი",
  pickup: "პიკაპი",
  convertible: "კაბრიოლეტი",
  minivan: "მინივენი",
  van: "ფურგონი",
};

type LooseCar = {
  plate?: string;
  type?: string;
  brand?: string;
  model?: string | { name?: string; type?: string; brand?: { name?: string } };
  image?: string | null;
};

export function bodyLabel(type?: string | null): string {
  if (!type || type === "Unknown") return "";
  return BODY_KA[type.toLowerCase()] ?? type;
}

export function carParts(car?: LooseCar | null): { title: string; short: string; type: string; plate: string; image: string | null } {
  if (!car) return { title: "", short: "", type: "", plate: "", image: null };
  const modelObj = car.model && typeof car.model === "object" ? car.model : null;
  const brand = (typeof car.brand === "string" ? car.brand : modelObj?.brand?.name) || "";
  const model = (typeof car.model === "string" ? car.model : modelObj?.name) || "";
  const type = car.type || modelObj?.type || "";
  const clean = (value: string) => (value && value !== "Unknown" ? value : "");
  const b = clean(brand);
  const m = clean(model);
  return {
    title: [b, m].filter(Boolean).join(" "),
    short: m || b,
    type: clean(type),
    plate: car.plate ?? "",
    image: car.image ?? null,
  };
}

export function carPhoto(type?: string | null, image?: string | null): string {
  if (image) return image;
  const key = (type ?? "").toLowerCase();
  if (SUV_TYPES.has(key)) return CAR_SUV;
  return CAR_SEDAN;
}

export function branchPhoto(branch: { id: number; image?: string | null }): string {
  if (branch.image) return branch.image;
  const index = Math.abs(Number(branch.id) || 0) % BRANCH_FALLBACK.length;
  return BRANCH_FALLBACK[index];
}

export function washCells(total: number | null, remaining: number | null): boolean[] {
  if (!total || total <= 0 || remaining == null) return [];
  const cells = Math.min(total, 10);
  const filled = Math.round((Math.max(0, remaining) / total) * cells);
  return Array.from({ length: cells }, (_, i) => i < filled);
}

export function formatGel(price: number): string {
  const value = Number.isInteger(price) ? String(price) : price.toFixed(2);
  return `${value} ₾`;
}

export function hourSpan(start?: string | null, end?: string | null): string | null {
  if (!start || !end) return null;
  const short = (value: string) => value.replace(":00", "");
  return `${short(start)}–${short(end)}`;
}

export function isRoundTheClock(start?: string | null, end?: string | null): boolean {
  return start === "00:00" && end === "00:00";
}

export function kmBetween(aLat: number, aLng: number, bLat: number, bLng: number): number | null {
  if (![aLat, aLng, bLat, bLng].every(Number.isFinite)) return null;
  const rad = (deg: number) => (deg * Math.PI) / 180;
  const dLat = rad(bLat - aLat);
  const dLng = rad(bLng - aLng);
  const h =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(rad(aLat)) * Math.cos(rad(bLat)) * Math.sin(dLng / 2) ** 2;
  return 6371 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

export const PAYMENT_SESSION_KEY = "ap_payment_pending";

export type PendingPayment = {
  kind: "package" | "points";
  car?: string;
  washes?: number;
  months?: number;
  price?: number;
  renewal?: boolean;
  points?: number;
  at: string;
};

export function savePendingPayment(payment: PendingPayment) {
  sessionStorage.setItem(PAYMENT_SESSION_KEY, JSON.stringify(payment));
}

export function readPendingPayment(): PendingPayment | null {
  try {
    const raw = sessionStorage.getItem(PAYMENT_SESSION_KEY);
    if (!raw) return null;
    const data = JSON.parse(raw) as PendingPayment;
    if (!data || (data.kind !== "package" && data.kind !== "points")) return null;
    return data;
  } catch {
    return null;
  }
}
