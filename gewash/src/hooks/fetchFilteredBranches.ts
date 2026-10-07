import { type Branch } from "@/hooks/useFetchBranches";
import { branchIsOpen, formatHm } from "@/lib/format";
import { customFetch } from "@/utils/customFetch";

export function parseBranch(b: any): Branch {
  const [latStr, lngStr] = String(b.location ?? "0,0").split(",");
  const workStart = formatHm(b.work_start);
  const workEnd = formatHm(b.work_end);
  return {
    id: b.id,
    name: b.name,
    address: b.address,
    lat: parseFloat(latStr),
    lng: parseFloat(lngStr),
    phone: b.phone ?? b.manager?.phone ?? "",
    workStart,
    workEnd,
    isOpen: branchIsOpen(workStart, workEnd),
    manager: b.manager ?? null,
    services: b.services ?? [],
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
