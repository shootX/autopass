import { Link } from "react-router-dom";
import { useUserRole } from "@/hooks/useUserRole";
import { useTranslation } from "@/hooks/useTranslation";

export default function NotFound() {
  const role = useUserRole();
  const t = useTranslation();
  const home = role === "manager" ? "/" : "/home";

  return (
    <div className="v4-screen">
      <h1>{t("NotFound.title")}</h1>
      <Link className="ap-link" to={home}>{t("NotFound.home")}</Link>
    </div>
  );
}
