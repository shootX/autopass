import { formatPhone, initials } from "@/lib/format";

export function greetingName(first?: string | null, phone?: string | null): string {
  const name = (first ?? "").trim();
  if (name) return name;
  return formatPhone(phone);
}

export function avatarLabel(first?: string | null, last?: string | null, phone?: string | null): string {
  const mark = initials(first, last);
  if (mark !== "•") return mark;
  const digits = (phone ?? "").replace(/\D/g, "");
  if (digits.length >= 2) return digits.slice(-2);
  return "•";
}
