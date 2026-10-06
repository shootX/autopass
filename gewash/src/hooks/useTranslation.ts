import { useSelector } from "react-redux";
import type { RootState } from "@/store";

export function useTranslation() {
  const t = useSelector((state: RootState) => state.lang.translations);
  return (path: string): string => {
    return path.split(".").reduce((acc, key) => acc?.[key], t) || path;
  };
}
