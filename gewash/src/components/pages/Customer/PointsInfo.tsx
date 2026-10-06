import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { motion } from "framer-motion";
import { useTranslation } from "@/hooks/useTranslation";
import { buyPointsAndRedirect } from "@/lib/buyPoints";
import { leftArrowUrl } from "@/assets/staticUrls";
import cardIconYellow from "@/assets/icons/card_icon_yellow.svg";
import cardIconBlue from "@/assets/icons/card_icon_blue.svg";
import ticketIcon from "@/assets/icons/ticket_icon.svg";
import referralsIcon from "@/assets/icons/referals_icon.svg";

export default function PointsInfo() {
  const navigate = useNavigate();
  const t = useTranslation();
  const [sheetOpen, setSheetOpen] = useState(false);
  const [amount, setAmount] = useState("");
  const [buyLoading, setBuyLoading] = useState(false);

  const price = Number(amount) || 0;

  const howItWorks = [
    { icon: cardIconBlue, text: t("PointsInfo.how.step1") },
    { icon: referralsIcon, text: t("PointsInfo.how.step2") },
    { icon: ticketIcon,   text: t("PointsInfo.how.step3") },
  ];

  return (
    <div className="points-info-page">
      <header>
        <img
          onClick={() => navigate(-1)}
          src={leftArrowUrl}
          alt="Back"
        />
        {t("PointsInfo.header.title")}
        <span />
      </header>

      <div className="points-info-wrapper">
        <div className="points-info-promo">
          <h2 className="points-info-promo__title">{t("PointsInfo.promo.title")}</h2>
          <p className="points-info-promo__desc">{t("PointsInfo.promo.desc")}</p>
          <button className="points-info-promo__btn" onClick={() => setSheetOpen(true)}>
            <img src={cardIconYellow} alt="" />
            {t("PointsInfo.promo.btn")}
          </button>
        </div>

        <div className="points-info-how">
          <h2 className="points-info-how__title">{t("PointsInfo.how.title")}</h2>
          <ul className="points-info-how__list">
            {howItWorks.map((item, i) => (
              <li key={i} className="points-info-how__item">
                <img src={item.icon} alt="" />
                <span>{item.text}</span>
              </li>
            ))}
          </ul>
        </div>
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
            initial={{ y: 400 }}
            animate={{ y: 0 }}
            exit={{ y: 400 }}
            transition={{ type: "spring", stiffness: 300, damping: 30 }}
            className="buy-points-sheet"
          >
            <div className="drag-indicator" />
            <h3 className="buy-points-sheet__title">{t("PointsInfo.sheet.title")}</h3>

            <input
              className="buy-points-sheet__input"
              type="number"
              inputMode="numeric"
              placeholder={t("PointsInfo.sheet.placeholder")}
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
            />

            <div className="buy-points-sheet__row">
              <span className="buy-points-sheet__label">{t("PointsInfo.sheet.amountLabel")}</span>
              <span className="buy-points-sheet__price">₾ {price}</span>
            </div>

            <button
              className={`buy-points-sheet__btn${price > 0 ? " active" : ""}`}
              disabled={price === 0}
              onClick={async () => {
                const token = localStorage.getItem("access_token");
                if (!token || price <= 0 || buyLoading) return;

                try {
                  setBuyLoading(true);
                  // Save pending info for MyPoints after redirect back.
                  sessionStorage.setItem(
                    "pending_points_buy_amount",
                    String(price)
                  );
                  const returnTo = window.location.pathname + window.location.search;

                  setSheetOpen(false);
                  await buyPointsAndRedirect(price, returnTo);
                } catch (err) {
                  console.error(err);
                } finally {
                  setBuyLoading(false);
                }
              }}
            >
              {buyLoading ? "Loading..." : t("PointsInfo.sheet.buyBtn")}
            </button>
          </motion.div>
        </>
      )}
    </div>
  );
}
