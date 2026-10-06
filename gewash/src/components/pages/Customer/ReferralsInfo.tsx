import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { motion } from "framer-motion";
import { useTranslation } from "@/hooks/useTranslation";
import { customFetch } from "@/utils/customFetch";
import sharedIconYellow from "@/assets/icons/shared_icon_yellow.svg";
import sharedIconBlue from "@/assets/icons/shared_icon_blue.svg";
import referralsIcon from "@/assets/icons/referals_icon.svg";
import ticketIcon from "@/assets/icons/ticket_icon.svg";
import copyIcon from "@/assets/icons/coppy_icon.svg";
import telegramIcon from "@/assets/icons/telegram_referal.svg";
import viberIcon from "@/assets/icons/viber_referal.svg";
import whatsappIcon from "@/assets/icons/whatsapp_referal.svg";
import facebookIcon from "@/assets/icons/facebook_referal.svg";
import { leftArrowUrl } from "@/assets/staticUrls";

const MOCK_REFERRAL_LINK = "https://geocar.com/rnu2345681";

const SOCIALS = [
  {
    label: "Telegram",
    color: "#29A8E2",
    icon: telegramIcon,
    href: (link: string) => `https://t.me/share/url?url=${encodeURIComponent(link)}`,
  },
  {
    label: "Whatsapp",
    color: "#25D366",
    icon: whatsappIcon,
    href: (link: string) => `https://wa.me/?text=${encodeURIComponent(link)}`,
  },
  {
    label: "Viber",
    color: "#7360F2",
    icon: viberIcon,
    href: (link: string) => `https://geocar.ge/redirect.html?to=viber://forward?text=${encodeURIComponent(link)}`,
  },
  {
    label: "Facebook",
    color: "#1877F2",
    icon: facebookIcon,
    href: (link: string) => `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(link)}`,
  },
];

export default function ReferralsInfo() {
  const navigate = useNavigate();
  const t = useTranslation();
  const [sheetOpen, setSheetOpen] = useState(false);
  const [copied, setCopied] = useState(false);
  const [referralLink, setReferralLink] = useState(MOCK_REFERRAL_LINK);
  const [referrals, setReferrals] = useState<Array<{ id: number; name: string; phone?: string }>>(
    []
  );

  useEffect(() => {
    const token = localStorage.getItem("access_token");
    if (!token) return;

    const loadReferrals = async () => {
      try {
        const res = await customFetch(`${import.meta.env.VITE_API_URL}/referrals/list`, {
          headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
        });
        if (!res.ok) return;
        const data = (await res.json()) as any;
        const list = (data?.referrals ?? data?.data ?? []) as any[];
        if (!Array.isArray(list)) return;

        const mapped = list
          .map((r, idx) => {
            const id = Number(r?.id ?? idx + 1);
            const name =
              typeof r?.name === "string"
                ? r.name
                : typeof r?.full_name === "string"
                  ? r.full_name
                  : typeof r?.username === "string"
                    ? r.username
                    : "Referral";
            const phone = typeof r?.phone === "string" ? r.phone : undefined;
            return { id, name, phone };
          })
          .filter(Boolean);

        setReferrals(mapped);
      } catch (err) {
        console.error("Failed to load referrals list:", err);
      }
    };

    loadReferrals();
  }, []);

  useEffect(() => {
    if (!sheetOpen) return;
    const token = localStorage.getItem("access_token");
    if (!token) return;

    const loadReferralLink = async () => {
      try {
        const res = await customFetch(`${import.meta.env.VITE_API_URL}/me/get-referral-link`, {
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: "application/json",
          },
        });
        if (!res.ok) return;

        const data = (await res.json()) as Record<string, unknown>;
        const nested = (data.data && typeof data.data === "object" ? data.data : null) as
          | Record<string, unknown>
          | null;

        const link =
          (typeof data.referral_link === "string" && data.referral_link) ||
          (typeof data.referralLink === "string" && data.referralLink) ||
          (typeof data.link === "string" && data.link) ||
          (typeof data.url === "string" && data.url) ||
          (typeof nested?.referral_link === "string" && nested.referral_link) ||
          (typeof nested?.link === "string" && nested.link) ||
          null;

        if (link) setReferralLink(link);
      } catch (err) {
        console.error("Failed to load referral link:", err);
      }
    };

    loadReferralLink();
  }, [sheetOpen]);

  const handleCopy = () => {
    navigator.clipboard.writeText(referralLink);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const howItWorks = [
    { icon: sharedIconBlue, text: t("ReferralsInfo.how.step1") },
    { icon: referralsIcon, text: t("ReferralsInfo.how.step2") },
    { icon: ticketIcon, text: t("ReferralsInfo.how.step3") },
  ];

  return (
    <div className="referrals-info-page">
      <header>
        <img
          onClick={() => navigate(-1)}
          src={leftArrowUrl}
          alt="Back"
        />
        {t("ReferralsInfo.header.title")}
        <span />
      </header>

      <div className="referrals-info-wrapper">
        <div className="referrals-info-promo">
          <h2 className="referrals-info-promo__title">{t("ReferralsInfo.promo.title")}</h2>
          <p className="referrals-info-promo__desc">{t("ReferralsInfo.promo.desc")}</p>
          <button className="referrals-info-promo__btn" onClick={() => setSheetOpen(true)}>
            <img src={sharedIconYellow} alt="" />
            {t("ReferralsInfo.promo.btn")}
          </button>
        </div>

        <div className="referrals-info-how">
          <h2 className="referrals-info-how__title">{t("ReferralsInfo.how.title")}</h2>
          <ul className="referrals-info-how__list">
            {howItWorks.map((item, i) => (
              <li key={i} className="referrals-info-how__item">
                <img src={item.icon} alt="" />
                <span>{item.text}</span>
              </li>
            ))}
          </ul>
        </div>

        {referrals.length > 0 && (
          <div className="referrals-info-list">
            <h2 className="referrals-info-list__title">My referrals</h2>
            <ul className="referrals-info-list__items">
              {referrals.map((r) => (
                <li key={r.id} className="referrals-info-list__item">
                  <span className="referrals-info-list__name">{r.name}</span>
                  {r.phone && <span className="referrals-info-list__phone">{r.phone}</span>}
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      {sheetOpen && (
        <>
          <div
            onClick={() => setSheetOpen(false)}
            style={{
              position: "fixed",
              inset: 0,
              backgroundColor: "rgba(24, 61, 105, 0.5)",
              zIndex: 999,
            }}
          />
          <motion.div
            drag="y"
            dragConstraints={{ top: 0, bottom: 100 }}
            dragElastic={0.2}
            onDragEnd={(_, info) => { if (info.offset.y > 50) setSheetOpen(false); }}
            initial={{ y: 500 }}
            animate={{ y: 0 }}
            exit={{ y: 500 }}
            transition={{ type: "spring", stiffness: 300, damping: 30 }}
            className="referral-link-sheet"
          >
            <div className="drag-indicator" />
            <h3 className="referral-link-sheet__title">{t("ReferralsInfo.sheet.title")}</h3>

            <p className="referral-link-sheet__label">{t("ReferralsInfo.sheet.yourLink")}</p>
            <div className="referral-link-sheet__input-row">
              <span className="referral-link-sheet__link">{referralLink}</span>
              <button
                className={`referral-link-sheet__copy${copied ? " copied" : ""}`}
                onClick={handleCopy}
              >
                {copied ? (
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M20 6L9 17l-5-5" stroke="#17BA68" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                ) : (
                  <img src={copyIcon} alt="copy" width={24} height={24} />
                )}
              </button>
            </div>

            <p className="referral-link-sheet__share-label">{t("ReferralsInfo.sheet.shareTo")}</p>
            <div className="referral-link-sheet__socials">
              {SOCIALS.map((s) => (
                <a
                  key={s.label}
                  href={s.href(referralLink)}
                  target="_blank"
                  className="referral-link-sheet__social"
                >
                  <div
                    className="referral-link-sheet__social-circle"
                    style={{ backgroundColor: s.color }}
                  >
                    <img src={s.icon} alt={s.label} />
                  </div>
                  <span>{s.label}</span>
                </a>
              ))}
            </div>
          </motion.div>
        </>
      )}
    </div>
  );
}
