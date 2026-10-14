import { useSelector } from "react-redux";
import type { RootState } from "@/store";

export function useTranslation() {
  const t = useSelector((state: RootState) => state.lang.translations);
  return (path: string): string => {
    const value = path.split(".").reduce<unknown>((acc, key) => {
      if (acc && typeof acc === "object") return (acc as Record<string, unknown>)[key];
      return undefined;
    }, t);
    return (typeof value === "string" ? value : "") || path;
  };
}
