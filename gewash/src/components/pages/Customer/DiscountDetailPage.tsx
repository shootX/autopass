import { useEffect, useState } from "react";
import { useNavigate, useLocation, useParams } from "react-router-dom";
import { motion, AnimatePresence } from "framer-motion";
import { useTranslation } from "@/hooks/useTranslation";
import { useDispatch, useSelector } from "react-redux";
import type { RootState } from "@/store";
import type { AppDispatch } from "@/store";
import geocoinIcon from "@/assets/images/shop/geocoin_icon.png";
import discountImg from "@/assets/images/discounts/discount_img.png";
import copyIcon from "@/assets/icons/coppy_icon.svg";
import { buyPointsAndRedirect } from "@/lib/buyPoints";
import {
  buyVoucher,
  extractVoucherCode,
  fetchMyVouchers,
  fetchVouchers,
  formatVoucherCodeDisplay,
} from "@/lib/shopApi";
import { refreshCurrentUser } from "@/hooks/useUser";
import ShopAsyncLoader from "@/components/ui/ShopAsyncLoader";

type SheetState = "closed" | "confirm" | "not_enough";

type CardData = {
  id?: number;
  title: string;
  subtitle: string;
  discount: string;
  coins: number;
  img?: string;
};

type DetailLocationState = Partial<CardData> & {
  fromMyArea?: boolean;
  /** Код ваучера при переходе из My Area */
  code?: string;
};

/** Полные данные каталога (цена > 0) — тогда не дергаем /vouchers повторно. */
function isCatalogComplete(p: Partial<CardData> | null | undefined): boolean {
  return (
    Boolean(p?.title) &&
    typeof p?.coins === "number" &&
    p.coins > 0
  );
}

const MOCK_DETAIL: CardData = {
  title: "Insurance from TBC Insurance with a 15% discount.",
  subtitle: "Protect your car from any risks! Take out a comprehensive insurance policy from TBC Insurance right now and get a 15% discount. Reliable insurance has become even more affordable.",
  discount: "-15%",
  coins: 150,
};

const TERMS = [
  "The promotion is valid from October 22, 2025, to November 30, 2025, inclusive. Contracts concluded after this date will be executed at standard rates.",
  "The discount is 15% of the total insurance premium (annual payment) under the new insurance contract.",
  "The discount cannot be combined with other promotional offers, special rates, or discount programs from TBC Insurance.",
];

export default function DiscountDetailPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { id: routeId } = useParams();
  const t = useTranslation();
  const dispatch = useDispatch<AppDispatch>();

  const passed = location.state as DetailLocationState | null;
  const fromMyArea = Boolean(passed?.fromMyArea);
  const [resolved, setResolved] = useState<Partial<CardData> | null>(null);
  const [catalogLoading, setCatalogLoading] = useState(
    () => !isCatalogComplete(passed) && Boolean(routeId),
  );
  const routeIdNum = routeId ? Number(routeId) : NaN;
  const card: CardData = {
    ...MOCK_DETAIL,
    ...passed,
    ...resolved,
    id: passed?.id ?? resolved?.id ?? (Number.isFinite(routeIdNum) ? routeIdNum : undefined),
  };

  const [sheet, setSheet] = useState<SheetState>("closed");
  const [bannerVisible, setBannerVisible] = useState(false);
  const [copied, setCopied] = useState(false);
  const [redeemCode, setRedeemCode] = useState<string | null>(() => {
    if (!passed?.fromMyArea) return null;
    const c = passed.code?.trim();
    return c || null;
  });

  const userPoints = useSelector((s: RootState) => s.user.data?.points);
  const [balance, setBalance] = useState(0);

  useEffect(() => {
    if (typeof userPoints === "number") setBalance(userPoints);
  }, [userPoints]);

  useEffect(() => {
    if (isCatalogComplete(passed)) {
      setCatalogLoading(false);
      return;
    }
    if (!routeId) {
      setCatalogLoading(false);
      return;
    }
    const vid = Number(routeId);
    if (!Number.isFinite(vid)) {
      setCatalogLoading(false);
      return;
    }
    setCatalogLoading(true);
    let cancelled = false;
    void fetchVouchers()
      .then((list) => {
        if (cancelled) return;
        const v = list.find((x) => x.id === vid);
        if (v) {
          const desc = v.description?.trim();
          setResolved({
            id: v.id,
            title: v.title,
            subtitle: desc || v.category,
            discount: v.discount,
            coins: v.coins,
            img: v.img || discountImg,
          });
        }
      })
      .catch(() => {})
      .finally(() => {
        if (!cancelled) setCatalogLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [passed?.title, passed?.coins, routeId]);

  // Только из My Area без кода в state — подтянуть код из API (не помечаем «куплен» для витрины)
  useEffect(() => {
    if (!fromMyArea) return;
    if (redeemCode) return;
    const vid = card.id ?? (routeId ? Number(routeId) : NaN);
    if (!Number.isFinite(vid) || vid <= 0) return;
    let cancelled = false;
    void fetchMyVouchers()
      .then((list) => {
        if (cancelled) return;
        const owned = list.find((x) => x.voucherId === vid);
        if (owned?.code?.trim()) setRedeemCode(owned.code.trim());
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, [fromMyArea, redeemCode, card.id, routeId]);

  const newBalance = balance - card.coins;

  const handleBuy = () => {
    if (balance >= card.coins) {
      setSheet("confirm");
    } else {
      setSheet("not_enough");
    }
  };

  const handleTake = async () => {
    const vid = card.id ?? (routeId ? Number(routeId) : NaN);
    if (!Number.isFinite(vid) || vid <= 0) {
      console.error("Missing voucher id");
      return;
    }
    try {
      const json = await buyVoucher(vid);
      await refreshCurrentUser(dispatch);
      let code = extractVoucherCode(json)?.trim();
      if (!code) {
        const list = await fetchMyVouchers();
        code = list.find((x) => x.voucherId === vid)?.code?.trim();
      }
      setRedeemCode(code ?? "");
      setSheet("closed");
      setBannerVisible(true);
      setTimeout(() => setBannerVisible(false), 3000);
    } catch (e) {
      console.error(e);
    }
  };

  const handleCopy = () => {
    const raw = (redeemCode ?? "").replace(/[\s-]/g, "");
    if (!raw) return;
    navigator.clipboard.writeText(raw).catch(() => {});
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  };

  useEffect(() => {
    // Prevent page scroll while bottom sheet is open (avoids background "moving").
    if (sheet === "closed") return;
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = prevOverflow;
    };
  }, [sheet]);

  const handleSheetDragEnd = (_: unknown, info: { offset: { y: number } }) => {
    // Drag down enough -> close, otherwise snap back.
    if (info.offset.y > 90) setSheet("closed");
  };

  if (catalogLoading) {
    return (
      <div className="discount-detail discount-detail--catalog-loading">
        <div className="discount-detail__loading-appbar">
          <button type="button" className="discount-detail__back" onClick={() => navigate(-1)}>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
              <path d="M15 18l-6-6 6-6" stroke="#183D69" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
            </svg>
          </button>
          <div className="discount-detail__balance">
            <img src={geocoinIcon} alt="coin" />
            <span>{balance.toLocaleString()}</span>
          </div>
        </div>
        <ShopAsyncLoader />
      </div>
    );
  }

  return (
    <div className="discount-detail">
      {/* Hero image */}
      <div className="discount-detail__hero">
        <img src={card.img ?? discountImg} alt={card.title} />

        {/* AppBar over hero */}
        <div className="discount-detail__appbar">
          <button className="discount-detail__back" onClick={() => navigate(-1)}>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
              <path d="M15 18l-6-6 6-6" stroke="#183D69" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
            </svg>
          </button>
          <div className="discount-detail__balance">
            <img src={geocoinIcon} alt="coin" />
            <span>{balance.toLocaleString()}</span>
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="discount-detail__body">

        {/* Success banner */}
        <AnimatePresence>
          {bannerVisible && (
            <motion.div
              className="discount-detail__success-banner"
              initial={{ opacity: 0, y: -8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -8 }}
              transition={{ duration: 0.3 }}
            >
              {t("Discounts.detail.successMsg")}
            </motion.div>
          )}
        </AnimatePresence>

        {/* Код: из My Area или после покупки на этой странице (не показываем «уже куплен» при заходе с витрины) */}
        {redeemCode && (
          <div className="discount-detail__code-block">
            <p className="discount-detail__code-label">{t("Discounts.detail.yourCode")}</p>
            <div className="discount-detail__code-row">
              <span className="discount-detail__code">{formatVoucherCodeDisplay(redeemCode)}</span>
              <button className="discount-detail__code-copy" onClick={handleCopy}>
                {copied ? (
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M5 12l5 5L20 7" stroke="#183D69" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                  </svg>
                ) : (
                  <img src={copyIcon} alt="copy" width="20" height="20" />
                )}
              </button>
            </div>
          </div>
        )}

        {/* Цена — только режим витрины; из My Area смотрим только код */}
        {!fromMyArea && (
          <div className="discount-detail__price-row">
            <span className="discount-detail__discount-badge">{card.discount}</span>
            <div className="discount-detail__coin-badge">
              <img src={geocoinIcon} alt="coin" />
              <span>{card.coins.toLocaleString()}</span>
            </div>
          </div>
        )}

        {/* Title + desc */}
        <div className="discount-detail__info">
          <h1 className="discount-detail__title">{card.title}</h1>
          <p className="discount-detail__desc">{card.subtitle}</p>
        </div>

        {/* Terms */}
        <div className="discount-detail__terms">
          <p className="discount-detail__terms-title">{t("Discounts.detail.termsTitle")}</p>
          <ul className="discount-detail__terms-list">
            {TERMS.map((term, i) => (
              <li key={i}>{term}</li>
            ))}
          </ul>
        </div>

        {/* Company info */}
        <div className="discount-detail__company">
          <div className="discount-detail__company-left">
            <p className="discount-detail__company-name">TBC Insurance</p>
            <p className="discount-detail__company-contact">Tel: 0322 573451</p>
            <p className="discount-detail__company-contact">Web: www.tegeta.ge</p>
          </div>
          <div className="discount-detail__company-right">
            <span>Tbilisi</span>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M8 1.5C5.515 1.5 3.5 3.515 3.5 6c0 3.75 4.5 8.5 4.5 8.5S12.5 9.75 12.5 6c0-2.485-2.015-4.5-4.5-4.5Zm0 6.125A1.625 1.625 0 1 1 8 4.25a1.625 1.625 0 0 1 0 3.25Z" fill="#183D69"/>
            </svg>
          </div>
        </div>

        {/* Покупка — сколько угодно раз с витрины; из My Area только просмотр кода */}
        {!fromMyArea && (
          <button className="discount-detail__buy-btn" onClick={handleBuy}>
            {t("Discounts.detail.buy")}
          </button>
        )}
      </div>

      {/* Overlay */}
      <AnimatePresence>
        {sheet !== "closed" && (
          <motion.div
            className="discount-detail__overlay"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={() => setSheet("closed")}
          />
        )}
      </AnimatePresence>

      {/* Bottom sheet */}
      <AnimatePresence>
        {sheet !== "closed" && (
          <motion.div
            className="discount-detail__sheet"
            initial={{ y: "100%" }}
            animate={{ y: 0 }}
            exit={{ y: "100%" }}
            transition={{ type: "tween", duration: 0.25 }}
            drag="y"
            dragConstraints={{ top: 0, bottom: 600 }}
            dragElastic={0}
            onDragEnd={handleSheetDragEnd}
          >
            <div className="discount-detail__sheet-handle" />

            {sheet === "not_enough" ? (
              <div className="discount-detail__sheet-notenough">
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
                  <circle cx="24" cy="24" r="24" fill="#F7B233" fillOpacity="0.15"/>
                  <path d="M24 14v14M24 32v2" stroke="#F7B233" strokeWidth="2.5" strokeLinecap="round"/>
                </svg>
                <p className="discount-detail__sheet-notenough-title">{t("Discounts.detail.notEnoughTitle")}</p>
                <p className="discount-detail__sheet-notenough-desc">{t("Discounts.detail.notEnoughDesc")}</p>
                <button
                  className="discount-detail__buy-btn"
                  onClick={async () => {
                    const shortage = Math.max(0, card.coins - balance);
                    if (shortage <= 0) {
                      setSheet("confirm");
                      return;
                    }
                    sessionStorage.setItem("pending_points_buy_amount", String(shortage));
                    const returnTo = window.location.pathname + window.location.search;
                    await buyPointsAndRedirect(shortage, returnTo);
                  }}
                >
                  {t("Discounts.detail.buyPoints")}
                </button>
              </div>
            ) : (
              <>
                <h2 className="discount-detail__sheet-title">{card.title}</h2>
                <p className="discount-detail__sheet-desc">{card.subtitle}</p>

                <div className="discount-detail__sheet-price">
                  <div className="discount-detail__coin-badge">
                    <img src={geocoinIcon} alt="coin" />
                    <span>{card.coins.toLocaleString()}</span>
                  </div>
                </div>

                <div className="discount-detail__sheet-divider" />

                <div className="discount-detail__sheet-balances">
                  <div className="discount-detail__sheet-balance-row">
                    <span className="discount-detail__sheet-balance-label">{t("Discounts.detail.currentBalance")}</span>
                    <div className="discount-detail__sheet-balance-value">
                      <span>{balance.toLocaleString()}</span>
                      <span className="discount-detail__sheet-balance-unit">{t("Discounts.detail.points")}</span>
                    </div>
                  </div>
                  <div className="discount-detail__sheet-balance-row">
                    <span className="discount-detail__sheet-balance-label">{t("Discounts.detail.newBalance")}</span>
                    <div className="discount-detail__sheet-balance-value discount-detail__sheet-balance-value--new">
                      <span>{newBalance.toLocaleString()}</span>
                      <span className="discount-detail__sheet-balance-unit">{t("Discounts.detail.points")}</span>
                    </div>
                  </div>
                </div>

                <button className="discount-detail__buy-btn" onClick={handleTake}>
                  {t("Discounts.detail.take")}
                </button>
              </>
            )}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
