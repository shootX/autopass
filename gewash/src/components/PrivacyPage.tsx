import "@/styles/privacy.scss";
import { Privacy as ka } from "../../ka.json";
import { Privacy as en } from "../../en.json";
import { Privacy as ru } from "../../ru.json";
import type { PrivacyLang } from "@/lib/shellRoute";

const TEXT = { ka, en, ru };

export default function PrivacyPage({ lang }: { lang: PrivacyLang }) {
  const t = TEXT[lang];
  return (
    <div className="privacy">
      <h1>{t.title}</h1>
      <p>{t.intro}</p>
      <p>{t.readCarefully}</p>

      <h3>{t.collectTitle}</h3>
      <p>{t.collectLead}</p>

      <h4>{t.personalTitle}</h4>
      <p>{t.personalLead}</p>
      <ul>
        <li>{t.personalName}</li>
        <li>{t.personalPhone}</li>
        <li>{t.personalEmail}</li>
      </ul>

      <h4>{t.vehicleTitle}</h4>
      <p>{t.vehicleLead}</p>
      <ul>
        <li>{t.vehicleMake}</li>
        <li>{t.vehiclePlate}</li>
      </ul>

      <h4>{t.locationTitle}</h4>
      <p>{t.locationBody}</p>

      <h4>{t.financialTitle}</h4>
      <p>{t.financialBody}</p>

      <h4>{t.deviceTitle}</h4>
      <p>{t.deviceBody}</p>

      <h3>{t.useTitle}</h3>
      <p>{t.useLead}</p>
      <ul>
        <li>{t.useAccount}</li>
        <li>{t.usePlans}</li>
        <li>{t.useBookings}</li>
        <li>{t.useNotify}</li>
        <li>{t.useLocation}</li>
        <li>{t.useImprove}</li>
        <li>{t.useSupport}</li>
      </ul>

      <h3>{t.shareTitle}</h3>
      <p>{t.shareLead}</p>
      <ul>
        <li><strong>{t.shareProvidersLabel}</strong> {t.shareProviders}</li>
        <li><strong>{t.shareLegalLabel}</strong> {t.shareLegal}</li>
        <li><strong>{t.shareTransferLabel}</strong> {t.shareTransfer}</li>
      </ul>

      <h3>{t.retentionTitle}</h3>
      <p>{t.retentionBody}</p>
      <p><strong>{t.deletionLabel}</strong> {t.deletionBody}</p>

      <h3>{t.securityTitle}</h3>
      <p>{t.securityBody}</p>

      <h3>{t.childrenTitle}</h3>
      <p>{t.childrenBody}</p>

      <h3>{t.changesTitle}</h3>
      <p>{t.changesBody}</p>

      <a className="back" href="/">{t.back}</a>
    </div>
  );
}
