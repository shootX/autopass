import { useEffect, useRef, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { useTranslation } from "@/hooks/useTranslation";
import { useSelector } from "react-redux";
import type { RootState } from "@/store";
import leftArrow from "@/assets/icons/left-arrow.svg";

import discountIcon from "@/assets/icons/discount_icon.svg";
import geocoinIcon from "@/assets/images/shop/geocoin_icon.png";
import discountImg from "@/assets/images/discounts/discount_img.png";
import ticket1 from "@/assets/images/shop/ticket_1.png";
import ShopAsyncLoader from "@/components/ui/ShopAsyncLoader";
import { fetchMyTickets, fetchMyVouchers, formatVoucherCodeDisplay } from "@/lib/shopApi";

const PER_PAGE = 6;

type AreaCard = {
  key: string;
  img: string;
  title: string;
  badge?: string;
  meta: string;
};

function chunkList<T>(items: T[], size: number): T[][] {
  const pages: T[][] = [];
  for (let i = 0; i < items.length; i += size) pages.push(items.slice(i, i + size));
  return pages;
}

function AreaRail({
  cards,
  onOpen,
}: {
  cards: AreaCard[];
  onOpen: (card: AreaCard) => void;
}) {
  const pages = chunkList(cards, PER_PAGE);
  const [page, setPage] = useState(0);
  const railRef = useRef<HTMLDivElement>(null);

  return (
    <div className="shop-offers-wrap">
      <div
        className="shop-offers-rail"
        ref={railRef}
        onScroll={() => {
          const rail = railRef.current;
          const slide = rail?.firstElementChild as HTMLElement | null;
          if (!rail || !slide) return;
          setPage(Math.round(rail.scrollLeft / (slide.offsetWidth + 10)));
        }}
      >
        {pages.map((group, pageIndex) => (
          <div className="shop-offers my-area-offers" key={pageIndex}>
            {group.map((card) => (
              <button key={card.key} type="button" className="shop-offer" onClick={() => onOpen(card)}>
                <span className="shop-offer__media">
                  <img src={card.img} alt={card.title} className="shop-offer__img" />
                  {card.badge ? <span className="shop-offer__badge">{card.badge}</span> : null}
                </span>
                <span className="shop-offer__body">
                  <span className="shop-offer__title">{card.title}</span>
                  {card.meta ? <span className="shop-offer__category">{card.meta}</span> : null}
                </span>
              </button>
            ))}
          </div>
        ))}
      </div>
      {pages.length > 1 && page < pages.length - 1 && (
        <span className="shop-offers-hint" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M9 6l6 6-6 6" stroke="#183D69" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </span>
      )}
      {pages.length > 1 && (
        <div className="shop-offers-dots">
          {pages.map((_, i) => (
            <span key={i} className={i === page ? "is-active" : ""} />
          ))}
        </div>
      )}
    </div>
  );
}

export default function MyAreaPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const t = useTranslation();
  const [activeTab, setActiveTab] = useState<"vouchers" | "tickets">(
    searchParams.get("tab") === "tickets" ? "tickets" : "vouchers",
  );
  const points = useSelector((s: RootState) => s.user.data?.points);
  const [myVouchers, setMyVouchers] = useState<Awaited<ReturnType<typeof fetchMyVouchers>>>([]);
  const [myTickets, setMyTickets] = useState<Awaited<ReturnType<typeof fetchMyTickets>>>([]);
  const [listStatus, setListStatus] = useState<"loading" | "ready" | "error">("loading");

  useEffect(() => {
    let cancelled = false;
    void Promise.all([fetchMyVouchers(), fetchMyTickets()])
      .then(([v, tick]) => {
        if (cancelled) return;
        setMyVouchers(v);
        setMyTickets(tick);
        setListStatus("ready");
      })
      .catch(() => {
        if (!cancelled) setListStatus("error");
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const loading = listStatus === "loading";
  const error = listStatus === "error";

  const voucherCards: AreaCard[] = myVouchers.map((v, index) => ({
    key: `voucher-${v.id}-${v.voucherId}-${index}`,
    img: v.img || discountImg,
    title: v.title,
    badge: v.discount || undefined,
    meta: v.code ? formatVoucherCodeDisplay(v.code) : "",
  }));

  const ticketCards: AreaCard[] = myTickets.map((row, index) => ({
    key: `ticket-${row.id}-${row.ticketId}-${index}`,
    img: row.img || ticket1,
    title: row.title,
    meta: String(row.qty),
  }));

  return (
    <div className="my-area-page">
      <header className="my-area-topbar">
        <button className="my-area-topbar__back" onClick={() => navigate(-1)} aria-label="Back">
          <img src={leftArrow} alt="Back" />
        </button>
        <span className="my-area-topbar__title">My Area</span>
        <div className="my-area-topbar__balance">
          <div className="my-area-topbar__coin">
            <img src={geocoinIcon} alt="coin" />
          </div>
          <div className="my-area-topbar__balance-text">
            <span className="my-area-topbar__amount">
              {typeof points === "number" ? points.toLocaleString() : "—"}
            </span>
          </div>
        </div>
      </header>

      <div className="my-area-wrapper">
        <div className="my-area-tabs">
          <button
            className={`my-area-tab${activeTab === "vouchers" ? " my-area-tab--active" : ""}`}
            onClick={() => setActiveTab("vouchers")}
          >
            {t("Shop.vouchers.title")}
          </button>
          <button
            className={`my-area-tab${activeTab === "tickets" ? " my-area-tab--active" : ""}`}
            onClick={() => setActiveTab("tickets")}
          >
            {t("Shop.tickets.title")}
          </button>
        </div>

        <section className="my-area-grid">
          {loading ? (
            <ShopAsyncLoader />
          ) : error ? (
            <p className="shop-async-error">{t("Shop.loadError")}</p>
          ) : activeTab === "vouchers" ? (
            voucherCards.length === 0 ? (
              <p className="shop-async-empty">{t("Shop.vouchers.empty")}</p>
            ) : (
              <AreaRail
                key="vouchers"
                cards={voucherCards}
                onOpen={(card) => {
                  const v = myVouchers.find((item, index) => `voucher-${item.id}-${item.voucherId}-${index}` === card.key);
                  if (!v) return;
                  navigate(`/shop/discounts/${v.voucherId}`, {
                    state: {
                      fromMyArea: true,
                      id: v.voucherId,
                      title: v.title,
                      subtitle: "",
                      discount: v.discount ?? "",
                      coins: 0,
                      img: v.img || discountImg,
                      code: v.code,
                    },
                  });
                }}
              />
            )
          ) : ticketCards.length === 0 ? (
            <p className="shop-async-empty">{t("Shop.tickets.mineEmpty")}</p>
          ) : (
            <AreaRail
              key="tickets"
              cards={ticketCards}
              onOpen={(card) => {
                const row = myTickets.find((item, index) => `ticket-${item.id}-${item.ticketId}-${index}` === card.key);
                if (!row) return;
                navigate(`/shop/giveaway/${row.ticketId}`, {
                  state: {
                    id: row.ticketId,
                    img: row.img || ticket1,
                    title: row.title,
                    daysLeft: 0,
                    ticketCost: row.qty,
                    coinPrice: 0,
                  },
                });
              }}
            />
          )}
          {!loading && !error && (
            <button
              type="button"
              className="shop-offers__more"
              onClick={() => navigate(activeTab === "vouchers" ? "/shop/discounts" : "/shop/giveaway")}
            >
              <span className="shop-more-card__plus">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                  <path d="M12 5v14M5 12h14" stroke="#183D69" strokeWidth="2" strokeLinecap="round" />
                </svg>
              </span>
              <span className="shop-more-card__label">
                {activeTab === "vouchers" ? (
                  <>
                    <span>{t("Shop.vouchers.buyMore")}</span>
                    <img src={discountIcon} alt="" />
                  </>
                ) : (
                  <span>{t("Shop.tickets.join")}</span>
                )}
              </span>
            </button>
          )}
        </section>
      </div>
    </div>
  );
}

