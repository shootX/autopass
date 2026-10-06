import { useSelector } from "react-redux";
import { type RootState } from "@/store";

export function useUserRole() {
  return useSelector((state: RootState) => state.user.role);
}
