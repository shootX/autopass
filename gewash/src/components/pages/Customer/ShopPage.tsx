import { useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useSelector } from "react-redux";
import { useTranslation } from "@/hooks/useTranslation";
import type { RootState } from "@/store";
import Sidebar from "@/components/ui/Sidebar";
import ShopAsyncLoader from "@/components/ui/ShopAsyncLoader";
import burgerIcon from "/icons/b-menu.svg";
import discountIcon from "@/assets/icons/discount_icon.svg";
import ticketIcon from "@/assets/icons/ticket_icon.svg";
import ticketYellow from "@/assets/images/shop/ticket_yellow.svg";
import rightArrow from "@/assets/icons/right-arrow.svg";
import geocoinIcon from "@/assets/images/shop/geocoin_icon.png";
import voucher1 from "@/assets/images/shop/voucher_1.png";
import voucher2 from "@/assets/images/shop/voucher_2.png";
import voucher3 from "@/assets/images/shop/voucher_3.png";
import ticket1 from "@/assets/images/shop/ticket_1.png";
import ticket2 from "@/assets/images/shop/ticket_2.png";
import ticket3 from "@/assets/images/shop/ticket_3.png";
import {
  formatVoucherCodeDisplay,
  loadShopBundle,
  peekShopBundle,
  type MyTicketEntry,
  type MyVoucherEntry,
  type ShopTicket,
  type ShopVoucher,
} from "@/lib/shopApi";

const VOUCHERS_PER_PAGE = 4;
const MY_VOUCHERS_PER_PAGE = 2;

function chunkList<T>(items: T[], size: number): T[][] {
  const pages: T[][] = [];
  for (let i = 0; i < items.length; i += size) pages.push(items.slice(i, i + size));
  return pages;
}

const V_FALLBACK = [voucher1, voucher2, voucher3];
const T_FALLBACK = [ticket1, ticket2, ticket3];

function patchVoucherImgs(list: ShopVoucher[]): ShopVoucher[] {
  return list.map((v, i) => ({
    ...v,
    img: v.img || V_FALLBACK[i % V_FALLBACK.length],
  }));
}

function patchTicketImgs(list: ShopTicket[]): ShopTicket[] {
  return list.map((t, i) => ({
    ...t,
    img: t.img || T_FALLBACK[i % T_FALLBACK.length],
  }));
}

function MyTicketRail({
  tickets,
  onOpen,
}: {
  tickets: MyTicketEntry[];
  onOpen: (ticket: MyTicketEntry) => void;
}) {
  const pages = chunkList(tickets, MY_VOUCHERS_PER_PAGE);
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
          <div className="shop-offers shop-offers--pair" key={pageIndex}>
            {group.map((row, rowIndex) => (
              <button key={`ticket-${pageIndex}-${row.id}-${row.ticketId}-${rowIndex}`} type="button" className="shop-offer" onClick={() => onOpen(row)}>
                <span className="shop-offer__media">
                  <img src={row.img || T_FALLBACK[0]} alt={row.title} className="shop-offer__img" />
                </span>
                <span className="shop-offer__body">
                  <span className="shop-offer__title">{row.title}</span>
                  <span className="shop-offer__meta">
                    <span className="shop-offer__category">{row.qty}</span>
                    <span className="shop-offer__price">
                      <img src={ticketYellow} alt="" />
                      {row.qty}
                    </span>
                  </span>
                </span>
              </button>
            ))}
          </div>
        ))}
      </div>
      {pages.length > 1 && page < pages.length - 1 && (
        <span className="shop-offers-hint" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M9 6l6 6-6 6" stroke="#183D69" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"/>
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

function MyVoucherRail({
  vouchers,
  onOpen,
}: {
  vouchers: MyVoucherEntry[];
  onOpen: (voucher: MyVoucherEntry) => void;
}) {
  const pages = chunkList(vouchers, MY_VOUCHERS_PER_PAGE);
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
          <div className="shop-offers shop-offers--pair" key={pageIndex}>
            {group.map((v, voucherIndex) => (
              <button key={`voucher-${pageIndex}-${v.id}-${v.voucherId}-${voucherIndex}`} type="button" className="shop-offer" onClick={() => onOpen(v)}>
                <span className="shop-offer__media">
                  <img src={v.img || V_FALLBACK[0]} alt={v.title} className="shop-offer__img" />
                  {v.discount ? <span className="shop-offer__badge">{v.discount}</span> : null}
                </span>
                <span className="shop-offer__body">
                  <span className="shop-offer__title">{v.title}</span>
                  {v.code ? (
                    <span className="shop-offer__category">{formatVoucherCodeDisplay(v.code)}</span>
                  ) : null}
                </span>
              </button>
            ))}
          </div>
        ))}
      </div>
      {pages.length > 1 && page < pages.length - 1 && (
        <span className="shop-offers-hint" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M9 6l6 6-6 6" stroke="#183D69" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"/>
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

export default function ShopPage() {
  const navigate = useNavigate();
  const t = useTranslation();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const points = useSelector((s: RootState) => s.user.data?.points);
  const cachedShop = peekShopBundle();
  const [vouchers, setVouchers] = useState<ShopVoucher[]>(cachedShop?.vouchers ?? []);
  const [tickets, setTickets] = useState<ShopTicket[]>(cachedShop?.tickets ?? []);
  const [myVouchers, setMyVouchers] = useState<MyVoucherEntry[]>(cachedShop?.myVouchers ?? []);
  const [myTickets, setMyTickets] = useState<MyTicketEntry[]>(cachedShop?.myTickets ?? []);
  const [listStatus, setListStatus] = useState<"loading" | "ready" | "error">(cachedShop ? "ready" : "loading");
  const [voucherPage, setVoucherPage] = useState(0);
  const [ticketPage, setTicketPage] = useState(0);
  const voucherRailRef = useRef<HTMLDivElement>(null);
  const ticketRailRef = useRef<HTMLDivElement>(null);
  const voucherPages = chunkList(vouchers, VOUCHERS_PER_PAGE);
  const ticketPages = chunkList(tickets, VOUCHERS_PER_PAGE);

  useEffect(() => {
    let cancelled = false;
    void loadShopBundle()
      .then((bundle) => {
        if (cancelled) return;
        setVouchers(patchVoucherImgs(bundle.vouchers));
        setTickets(patchTicketImgs(bundle.tickets));
        setMyVouchers(bundle.myVouchers);
        setMyTickets(bundle.myTickets);
        setListStatus("ready");
      })
      .catch(() => {
        if (!cancelled && !peekShopBundle()) setListStatus("error");
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const loading = listStatus === "loading";
  const error = listStatus === "error";

  return (
    <div className="shop-page">
      {sidebarOpen && <Sidebar onClose={() => setSidebarOpen(false)} />}
      <header className="shop-header">
        <button className="shop-header__burger" onClick={() => setSidebarOpen(true)}>
          <img src={burgerIcon} alt="menu" width="20" height="18" />
        </button>
        <span className="shop-header__title">{t("Shop.header.title")}</span>
        <span className="shop-header__spacer" />
      </header>

      <div className="shop-wrapper">
        {/* Balance Card */}
        <div className="shop-balance">
          <div className="shop-balance__left">
            <div className="shop-balance__coin">
              <img src={geocoinIcon} alt="coin" />
            </div>
            <span className="shop-balance__label">{t("Shop.balance.label")}</span>
          </div>
          <div className="shop-balance__right">
            <span className="shop-balance__amount">
              {typeof points === "number" ? points.toLocaleString() : "—"}
            </span>
            <span className="shop-balance__unit">{t("Shop.balance.unit")}</span>
          </div>
        </div>

        {/* Category Cards */}
        <div className="shop-categories">
          <div className="shop-category" onClick={() => navigate("/shop/discounts")}>
            <div className="shop-category__left">
              <div className="shop-category__icon-wrap">
                <img src={discountIcon} alt="discounts" />
              </div>
              <span className="shop-category__label">{t("Shop.categories.discounts")}</span>
            </div>
            <img src={rightArrow} alt="" className="shop-category__arrow" />
          </div>
          <div className="shop-category" onClick={() => navigate("/shop/giveaway")}>
            <div className="shop-category__left">
              <div className="shop-category__icon-wrap">
                <img src={ticketIcon} alt="giveaway" />
              </div>
              <span className="shop-category__label">{t("Shop.categories.giveaway")}</span>
            </div>
            <img src={rightArrow} alt="" className="shop-category__arrow" />
          </div>
        </div>

        {error && <p className="shop-async-error">{t("Shop.loadError")}</p>}

        {/* My Vouchers */}
        <section className="shop-section">
          <div className="shop-section__header">
            <h2 className="shop-section__title">{t("Shop.categories.discounts")}</h2>
            <button className="shop-section__all" onClick={() => navigate("/shop/discounts")}>
              {t("Shop.all")}
              <img src={rightArrow} alt="" />
            </button>
          </div>
          {loading ? (
            <ShopAsyncLoader />
          ) : error ? null : vouchers.length === 0 ? (
            <p className="shop-async-empty">{t("Shop.vouchers.empty")}</p>
          ) : (
            <div className="shop-offers-wrap">
              <div
                className="shop-offers-rail"
                ref={voucherRailRef}
                onScroll={() => {
                  const rail = voucherRailRef.current;
                  const slide = rail?.firstElementChild as HTMLElement | null;
                  if (!rail || !slide) return;
                  const step = slide.offsetWidth + 10;
                  setVoucherPage(Math.round(rail.scrollLeft / step));
                }}
              >
                {voucherPages.map((page, pageIndex) => (
                  <div className="shop-offers" key={pageIndex}>
                    {page.map((v, itemIndex) => (
                      <button
                        key={`shop-voucher-${pageIndex}-${itemIndex}-${v.id}`}
                        type="button"
                        className="shop-offer"
                        onClick={() =>
                          navigate(`/shop/discounts/${v.id}`, {
                            state: {
                              id: v.id,
                              title: v.title,
                              subtitle: v.category,
                              discount: v.discount,
                              coins: v.coins,
                              img: v.img,
                            },
                          })
                        }
                      >
                        <span className="shop-offer__media">
                          <img src={v.img} alt={v.title} className="shop-offer__img" />
                          <span className="shop-offer__badge">{v.discount}</span>
                        </span>
                        <span className="shop-offer__body">
                          <span className="shop-offer__title">{v.title}</span>
                          <span className="shop-offer__meta">
                            <span className="shop-offer__category">{v.category}</span>
                            <span className="shop-offer__price">
                              <img src={geocoinIcon} alt="" />
                              {v.coins.toLocaleString()}
                            </span>
                          </span>
                        </span>
                      </button>
                    ))}
                  </div>
                ))}
              </div>
              {voucherPages.length > 1 && voucherPage < voucherPages.length - 1 && (
                <span className="shop-offers-hint" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M9 6l6 6-6 6" stroke="#183D69" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"/>
                  </svg>
                </span>
              )}
              {voucherPages.length > 1 && (
                <div className="shop-offers-dots">
                  {voucherPages.map((_, i) => (
                    <span key={i} className={i === voucherPage ? "is-active" : ""} />
                  ))}
                </div>
              )}
            </div>
          )}
        </section>

        {/* Giveaways for sale */}
        <section className="shop-section">
          <div className="shop-section__header">
            <h2 className="shop-section__title">{t("Shop.categories.giveaway")}</h2>
            <button className="shop-section__all" onClick={() => navigate("/shop/giveaway")}>
              {t("Shop.all")}
              <img src={rightArrow} alt="" />
            </button>
          </div>
          {loading ? (
            <ShopAsyncLoader />
          ) : error ? null : tickets.length === 0 ? (
            <p className="shop-async-empty">{t("Shop.tickets.empty")}</p>
          ) : (
            <div className="shop-offers-wrap">
              <div
                className="shop-offers-rail"
                ref={ticketRailRef}
                onScroll={() => {
                  const rail = ticketRailRef.current;
                  const slide = rail?.firstElementChild as HTMLElement | null;
                  if (!rail || !slide) return;
                  setTicketPage(Math.round(rail.scrollLeft / (slide.offsetWidth + 10)));
                }}
              >
                {ticketPages.map((page, pageIndex) => (
                  <div className="shop-offers" key={pageIndex}>
                    {page.map((ticket, itemIndex) => (
                      <button
                        key={`shop-ticket-${pageIndex}-${itemIndex}-${ticket.id}`}
                        type="button"
                        className="shop-offer"
                        onClick={() =>
                          navigate(`/shop/giveaway/${ticket.id}`, {
                            state: {
                              id: ticket.id,
                              img: ticket.img,
                              title: ticket.title,
                              daysLeft: ticket.daysLeft,
                              ticketCost: ticket.ticketCost,
                              coinPrice: ticket.coinPrice,
                            },
                          })
                        }
                      >
                        <span className="shop-offer__media">
                          <img src={ticket.img} alt={ticket.title} className="shop-offer__img" />
                          <span className="shop-offer__badge">{ticket.daysLeft}</span>
                        </span>
                        <span className="shop-offer__body">
                          <span className="shop-offer__title">{ticket.title}</span>
                          <span className="shop-offer__meta">
                            <span className="shop-offer__category">
                              {ticket.daysLeft} {t("Shop.tickets.daysLeft")}
                            </span>
                            <span className="shop-offer__price">
                              <img src={geocoinIcon} alt="" />
                              {ticket.coinPrice.toLocaleString()}
                            </span>
                          </span>
                        </span>
                      </button>
                    ))}
                  </div>
                ))}
              </div>
              {ticketPages.length > 1 && ticketPage < ticketPages.length - 1 && (
                <span className="shop-offers-hint" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M9 6l6 6-6 6" stroke="#183D69" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"/>
                  </svg>
                </span>
              )}
              {ticketPages.length > 1 && (
                <div className="shop-offers-dots">
                  {ticketPages.map((_, i) => (
                    <span key={i} className={i === ticketPage ? "is-active" : ""} />
                  ))}
                </div>
              )}
            </div>
          )}
        </section>

        <section className="shop-section">
          <div className="shop-section__header">
            <h2 className="shop-section__title">{t("Shop.vouchers.title")}</h2>
            <button className="shop-section__all" onClick={() => navigate("/my-area")}>
              {t("Shop.all")}
              <img src={rightArrow} alt="" />
            </button>
          </div>
          {loading ? (
            <ShopAsyncLoader />
          ) : myVouchers.length === 0 ? (
            <p className="shop-async-empty">{t("Shop.vouchers.empty")}</p>
          ) : (
            <MyVoucherRail
              vouchers={myVouchers}
              onOpen={(v) =>
                navigate(`/shop/discounts/${v.voucherId}`, {
                  state: {
                    fromMyArea: true,
                    id: v.voucherId,
                    title: v.title,
                    subtitle: "",
                    discount: v.discount ?? "",
                    coins: 0,
                    img: v.img,
                    code: v.code,
                  },
                })
              }
            />
          )}
          {!loading && !error && (
            <button type="button" className="shop-offers__more" onClick={() => navigate("/shop/discounts")}>
              <span className="shop-more-card__plus">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                  <path d="M12 5v14M5 12h14" stroke="#183D69" strokeWidth="2" strokeLinecap="round"/>
                </svg>
              </span>
              <span className="shop-more-card__label">
                <span>{t("Shop.vouchers.buyMore")}</span>
                <img src={discountIcon} alt="" />
              </span>
            </button>
          )}
        </section>

        <section className="shop-section">
          <div className="shop-section__header">
            <h2 className="shop-section__title">{t("Shop.tickets.title")}</h2>
            <button className="shop-section__all" onClick={() => navigate("/my-area?tab=tickets")}>
              {t("Shop.all")}
              <img src={rightArrow} alt="" />
            </button>
          </div>
          {loading ? (
            <ShopAsyncLoader />
          ) : myTickets.length === 0 ? (
            <p className="shop-async-empty">{t("Shop.tickets.mineEmpty")}</p>
          ) : (
            <MyTicketRail
              tickets={myTickets}
              onOpen={(row) =>
                navigate(`/shop/giveaway/${row.ticketId}`, {
                  state: {
                    id: row.ticketId,
                    img: row.img,
                    title: row.title,
                    daysLeft: 0,
                    ticketCost: row.qty,
                    coinPrice: 0,
                  },
                })
              }
            />
          )}
          {!loading && !error && (
            <button type="button" className="shop-offers__more" onClick={() => navigate("/shop/giveaway")}>
              <span className="shop-more-card__plus">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                  <path d="M12 5v14M5 12h14" stroke="#183D69" strokeWidth="2" strokeLinecap="round"/>
                </svg>
              </span>
              <span className="shop-more-card__label">
                <span>{t("Shop.tickets.join")}</span>
              </span>
            </button>
          )}
        </section>
      </div>
    </div>
  );
}
