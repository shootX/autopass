import "@/styles/privacy.scss";
import { Link } from "react-router-dom";
import { useTranslation } from "@/hooks/useTranslation";

export default function PrivacyPage() {
  const t = useTranslation();
  return (
    <div className="privacy">
      <h1>{t("Privacy.title")}</h1>
      <p>{t("Privacy.intro")}</p>
      <p>{t("Privacy.readCarefully")}</p>

      <h3>{t("Privacy.collectTitle")}</h3>
      <p>{t("Privacy.collectLead")}</p>

      <h4>{t("Privacy.personalTitle")}</h4>
      <p>{t("Privacy.personalLead")}</p>
      <ul>
        <li>{t("Privacy.personalName")}</li>
        <li>{t("Privacy.personalPhone")}</li>
        <li>{t("Privacy.personalEmail")}</li>
      </ul>

      <h4>{t("Privacy.vehicleTitle")}</h4>
      <p>{t("Privacy.vehicleLead")}</p>
      <ul>
        <li>{t("Privacy.vehicleMake")}</li>
        <li>{t("Privacy.vehiclePlate")}</li>
      </ul>

      <h4>{t("Privacy.locationTitle")}</h4>
      <p>{t("Privacy.locationBody")}</p>

      <h4>{t("Privacy.financialTitle")}</h4>
      <p>{t("Privacy.financialBody")}</p>

      <h4>{t("Privacy.deviceTitle")}</h4>
      <p>{t("Privacy.deviceBody")}</p>

      <h3>{t("Privacy.useTitle")}</h3>
      <p>{t("Privacy.useLead")}</p>
      <ul>
        <li>{t("Privacy.useAccount")}</li>
        <li>{t("Privacy.usePlans")}</li>
        <li>{t("Privacy.useBookings")}</li>
        <li>{t("Privacy.useNotify")}</li>
        <li>{t("Privacy.useLocation")}</li>
        <li>{t("Privacy.useImprove")}</li>
        <li>{t("Privacy.useSupport")}</li>
      </ul>

      <h3>{t("Privacy.shareTitle")}</h3>
      <p>{t("Privacy.shareLead")}</p>
      <ul>
        <li><strong>{t("Privacy.shareProvidersLabel")}</strong> {t("Privacy.shareProviders")}</li>
        <li><strong>{t("Privacy.shareLegalLabel")}</strong> {t("Privacy.shareLegal")}</li>
        <li><strong>{t("Privacy.shareTransferLabel")}</strong> {t("Privacy.shareTransfer")}</li>
      </ul>

      <h3>{t("Privacy.retentionTitle")}</h3>
      <p>{t("Privacy.retentionBody")}</p>
      <p><strong>{t("Privacy.deletionLabel")}</strong> {t("Privacy.deletionBody")}</p>

      <h3>{t("Privacy.securityTitle")}</h3>
      <p>{t("Privacy.securityBody")}</p>

      <h3>{t("Privacy.childrenTitle")}</h3>
      <p>{t("Privacy.childrenBody")}</p>

      <h3>{t("Privacy.changesTitle")}</h3>
      <p>{t("Privacy.changesBody")}</p>

      <br />
      <Link to="/">
        <button className="sign-in">{t("Privacy.back")}</button>
      </Link>
    </div>
  );
}
