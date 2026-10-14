import { type Branch } from "@/hooks/useFetchBranches";
import { branchIsOpen, formatHm } from "@/lib/format";
import { customFetch } from "@/utils/customFetch";

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) return null;
  return value as Record<string, unknown>;
}

function readText(value: unknown, fallback = ""): string {
  return typeof value === "string" ? value : fallback;
}

function readManager(value: unknown): Branch["manager"] {
  const manager = asRecord(value);
  if (!manager) return null;
  const id = typeof manager.id === "number" ? manager.id : Number(manager.id);
  return {
    id: Number.isFinite(id) ? id : 0,
    name: readText(manager.name),
    surname: readText(manager.surname),
    email: readText(manager.email),
    phone: readText(manager.phone),
  };
}

function readServices(value: unknown): Branch["services"] {
  if (!Array.isArray(value)) return [];
  return value.flatMap((item) => {
    const service = asRecord(item);
    if (!service) return [];
    const id = typeof service.id === "number" ? service.id : Number(service.id);
    if (!Number.isFinite(id)) return [];
    return [{ id, name: readText(service.name) }];
  });
}

export function parseBranch(raw: unknown): Branch {
  const b = asRecord(raw) ?? {};
  const [latStr, lngStr] = String(b.location ?? "0,0").split(",");
  const workStart = formatHm(typeof b.work_start === "string" ? b.work_start : null);
  const workEnd = formatHm(typeof b.work_end === "string" ? b.work_end : null);
  const id = typeof b.id === "number" ? b.id : Number(b.id);
  return {
    id: Number.isFinite(id) ? id : 0,
    name: readText(b.name),
    address: readText(b.address),
    lat: parseFloat(latStr),
    lng: parseFloat(lngStr),
    phone: readText(b.phone) || readText(asRecord(b.manager)?.phone),
    workStart,
    workEnd,
    isOpen: branchIsOpen(workStart, workEnd),
    manager: readManager(b.manager),
    services: readServices(b.services),
    image: readText(b.image) || readText(b.photo) || readText(b.picture) || null,
  };
}

export async function fetchFilteredBranches({
  selectedServices,
  onlyOpen,
  roundTheClockOnly,
}: {
  selectedServices: number[];
  onlyOpen: boolean;
  roundTheClockOnly: boolean;
}): Promise<Branch[]> {
  const token = localStorage.getItem("access_token");
  const params = new URLSearchParams();

  if (selectedServices.length > 0) {
    params.set("services", selectedServices.join(","));
  }
  if (onlyOpen) {
    params.set("open", "1");
  }
  if (roundTheClockOnly) {
    params.set("round", "1");
  }

  const res = await customFetch(`${import.meta.env.VITE_API_URL}/branches?${params.toString()}`, {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  });

  if (!res.ok) throw new Error("Failed to fetch filtered branches");

  const data = await res.json();

  const parsed = (data.branches ?? []).map(parseBranch);

  return parsed;
}

let branchesCache: Branch[] | null = null;
let branchesInflight: Promise<Branch[]> | null = null;

export function peekBranches(): Branch[] | null {
  return branchesCache;
}

export function loadDefaultBranches(): Promise<Branch[]> {
  if (branchesCache) return Promise.resolve(branchesCache);
  if (branchesInflight) return branchesInflight;
  branchesInflight = fetchFilteredBranches({
    selectedServices: [],
    onlyOpen: false,
    roundTheClockOnly: false,
  })
    .then((list) => {
      branchesCache = list;
      return list;
    })
    .finally(() => {
      branchesInflight = null;
    });
  return branchesInflight;
}
