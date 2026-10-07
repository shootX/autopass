export function formatPhone(raw?: string | null): string {
  if (!raw) return "";
  const digits = raw.replace(/\D/g, "");
  const local = digits.startsWith("995") ? digits.slice(3) : digits;
  if (local.length === 9) {
    return `+995 ${local.slice(0, 3)} ${local.slice(3, 5)} ${local.slice(5, 7)} ${local.slice(7)}`;
  }
  if (!digits) return raw;
  return raw.trim().startsWith("+") ? raw : `+${digits}`;
}

export function initials(first?: string | null, last?: string | null): string {
  const a = (first ?? "").trim().charAt(0);
  const b = (last ?? "").trim().charAt(0);
  return `${a}${b}` || "•";
}

const KA_MONTHS = ["იანვარი", "თებერვალი", "მარტი", "აპრილი", "მაისი", "ივნისი", "ივლისი", "აგვისტო", "სექტემბერი", "ოქტომბერი", "ნოემბერი", "დეკემბერი"];
const KA_MONTHS_SHORT = ["იან", "თებ", "მარ", "აპრ", "მაი", "ივნ", "ივლ", "აგვ", "სექ", "ოქტ", "ნოე", "დეკ"];
const KA_WEEKDAYS = ["კვირა", "ორშაბათი", "სამშაბათი", "ოთხშაბათი", "ხუთშაბათი", "პარასკევი", "შაბათი"];

export function formatKaDate(input: Date | string, mode: "long" | "short" | "weekday" = "long"): string {
  let date: Date;
  if (typeof input === "string" && /^\d{4}-\d{2}-\d{2}/.test(input)) {
    const [y, m, d] = input.slice(0, 10).split("-").map(Number);
    date = new Date(y, m - 1, d);
  } else {
    date = typeof input === "string" ? new Date(input) : input;
  }
  if (Number.isNaN(date.getTime())) return "";
  const day = date.getDate();
  const month = KA_MONTHS[date.getMonth()];
  if (mode === "short") return `${day} ${KA_MONTHS_SHORT[date.getMonth()]}`;
  if (mode === "weekday") return `${KA_WEEKDAYS[date.getDay()]}, ${day} ${month}`;
  return `${day} ${month}`;
}

export function formatKaMonth(input: Date): string {
  return `${KA_MONTHS[input.getMonth()]} ${input.getFullYear()}`;
}

export function formatHm(value?: string | null): string | null {
  if (!value) return null;
  const match = String(value).match(/(\d{2}):(\d{2})/);
  return match ? `${match[1]}:${match[2]}` : null;
}

export function branchIsOpen(start?: string | null, end?: string | null, now = new Date()): boolean | null {
  const open = formatHm(start);
  const close = formatHm(end);
  if (!open || !close) return null;
  if (open === "00:00" && close === "00:00") return true;
  const minutes = now.getHours() * 60 + now.getMinutes();
  const toMin = (hm: string) => {
    const [h, m] = hm.split(":").map(Number);
    return h * 60 + m;
  };
  const a = toMin(open);
  const b = toMin(close);
  if (a === b) return true;
  if (a < b) return minutes >= a && minutes <= b;
  return minutes >= a || minutes <= b;
}
