import { Switch } from "@/components/ui/switch";
import { useTranslation } from "@/hooks/useTranslation";

export default function NotificationSettings({
  wash,
  setWash,
  subscription,
  setSubscription,
  promo,
  setPromo,
}: {
  wash: boolean;
  setWash: (v: boolean) => void;
  subscription: boolean;
  setSubscription: (v: boolean) => void;
  promo: boolean;
  setPromo: (v: boolean) => void;
}) {
  const t = useTranslation();

  return (
    <div style={{ padding: "16px", backgroundColor: "#fff", borderRadius: "16px", boxShadow: "0 4px 12px rgba(0,0,0,0.08)", margin: "16px", fontFamily: "Roboto, sans-serif" }}>
      <h1 style={{ fontSize: "20px", color: "#183D69", fontWeight: 600, marginBottom: "24px" }}>
        {t("NotificationSettings.title")}
      </h1>

      <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "16px" }}>
        <p style={{ fontSize: "15px", color: "#183D69", fontWeight: 500 }}>{t("NotificationSettings.options.wash")}</p>
        <Switch checked={wash} onCheckedChange={setWash} />
      </div>

      <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "16px" }}>
        <p style={{ fontSize: "15px", color: "#183D69", fontWeight: 500 }}>{t("NotificationSettings.options.subscription")}</p>
        <Switch checked={subscription} onCheckedChange={setSubscription} />
      </div>

      <div style={{ display: "flex", justifyContent: "space-between" }}>
        <p style={{ fontSize: "15px", color: "#183D69", fontWeight: 500 }}>{t("NotificationSettings.options.promo")}</p>
        <Switch checked={promo} onCheckedChange={setPromo} />
      </div>
    </div>
  );
}
