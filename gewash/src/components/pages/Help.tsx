import { useState } from "react";
import "../../styles/help.scss";
import { leftArrowUrl } from "@/assets/staticUrls";
import { useNavigate } from "react-router-dom";
import { useTranslation } from "@/hooks/useTranslation";

const KEYS = ["services", "locations", "booking", "duration"] as const;

export default function Help() {
  const [openIndex, setOpenIndex] = useState<number | null>(null);
  const navigate = useNavigate();
  const t = useTranslation();

  return (
    <div>
      <header>
        <img onClick={() => navigate(-1)} src={leftArrowUrl} alt={t("Help.backAlt")} />
        {t("Help.title")}
        <span></span>
      </header>

      <div className="help-wrapper">
        {KEYS.map((key, index) => {
          const isOpen = openIndex === index;
          return (
            <div key={key} className="help-item" onClick={() => setOpenIndex(isOpen ? null : index)}>
              <div className="help-question">
                <p>{t(`Help.items.${key}.q`)}</p>
                <img
                  src={leftArrowUrl}
                  alt=""
                  className={`arrow-icon ${isOpen ? "open" : ""}`}
                />
              </div>
              <div className={`help-answer ${isOpen ? "expanded" : ""}`}>
                <p>{t(`Help.items.${key}.a`)}</p>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
