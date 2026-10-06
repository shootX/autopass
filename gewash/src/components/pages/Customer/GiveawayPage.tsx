import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { motion, AnimatePresence } from "framer-motion";
import { useTranslation } from "@/hooks/useTranslation";
import { useSelector } from "react-redux";
import type { RootState } from "@/store";
import geocoinIcon from "@/assets/images/shop/geocoin_icon.png";
import ticketYellow from "@/assets/images/shop/ticket_yellow.svg";
import giveaway1 from "@/assets/images/giveaway/giveaway_1.png";
import giveaway2 from "@/assets/images/giveaway/giveaway_2.png";
import giveaway3 from "@/assets/images/giveaway/giveaway_3.png";
import ShopAsyncLoader from "@/components/ui/ShopAsyncLoader";
import { fetchTickets, type ShopTicket } from "@/lib/shopApi";

export type GiveawayItem = ShopTicket;

const FALLBACK_IMGS = [giveaway2, giveaway1, giveaway3];

function withFallbackImg(items: ShopTicket[]): ShopTicket[] {
  return items.map((item, i) => ({
    ...item,
    img: item.img || FALLBACK_IMGS[i % FALLBACK_IMGS.length],
  }));
}

export default function GiveawayPage() {
  const navigate = useNavigate();
  const t = useTranslation();
  const points = useSelector((s: RootState) => s.user.data?.points);
  const [items, setItems] = useState<GiveawayItem[]>([]);
  const [status, setStatus] = useState<"loading" | "ready" | "error">("loading");

  useEffect(() => {
    let cancelled = false;
    void fetchTickets()
      .then((list) => {
        if (cancelled) return;
        setItems(withFallbackImg(list));
        setStatus("ready");
      })
      .catch(() => {
        if (!cancelled) setStatus("error");
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="giveaway-page">
      <header className="giveaway-header">
        <button className="giveaway-header__back" onClick={() => navigate(-1)}>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path d="M15 18l-6-6 6-6" stroke="#183D69" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
          </svg>
        </button>
        <span className="giveaway-header__title">{t("Giveaway.title")}</span>
        <div className="giveaway-header__balance">
          <img src={geocoinIcon} alt="coin" />
          <span>{typeof points === "number" ? points.toLocaleString() : "—"}</span>
        </div>
      </header>

      <div className="giveaway-wrapper">
        <h2 className="giveaway-section-title">{t("Giveaway.activeOffers")}</h2>

        {status === "loading" ? (
          <ShopAsyncLoader />
        ) : status === "error" ? (
          <p className="shop-async-error">{t("Shop.loadError")}</p>
        ) : items.length === 0 ? (
          <p className="shop-async-empty">{t("Giveaway.empty")}</p>
        ) : (
          items.map((item, index) => (
            <div
              key={item.id}
              className="giveaway-card"
              onClick={() => navigate(`/shop/giveaway/${item.id}`, { state: item })}
            >
              <div className="giveaway-card__img-wrap">
                <img src={item.img} alt={item.title} className="giveaway-card__img" />
                {index === 0 && (
                  <div className="giveaway-card__ticket-badge">
                    <img src={ticketYellow} alt="ticket" />
                    <span>{item.ticketCost}</span>
                  </div>
                )}
              </div>

              <div className="giveaway-card__body">
                <div className="giveaway-card__info-row">
                  <div className="giveaway-card__text">
                    <p className="giveaway-card__title">{item.title}</p>
                    <p className="giveaway-card__days">{item.daysLeft} {t("Giveaway.daysLeft")}</p>
                  </div>
                  <div className="giveaway-card__price-badge">
                    <img src={geocoinIcon} alt="coin" />
                    <span>{item.coinPrice}</span>
                  </div>
                </div>
              </div>

              <div className="giveaway-card__tear" />
            </div>
          ))
        )}
      </div>
    </div>
  );
}
