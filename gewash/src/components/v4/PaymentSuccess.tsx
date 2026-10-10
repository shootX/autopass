import { useMemo } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { Check, X } from "lucide-react";
import { formatGel, readPendingPayment } from "@/lib/v4";

function param(params: URLSearchParams, keys: string[]): string | null {
  for (const key of keys) {
    const value = params.get(key);
    if (value && value.trim()) return value.trim();
  }
  return null;
}

function money(raw: string | null, stored?: number): string | null {
  if (stored != null && Number.isFinite(stored)) return formatGel(stored);
  if (!raw) return null;
  const n = Number(raw);
  if (!Number.isFinite(n)) return null;
  const gel = n >= 1000 && Number.isInteger(n) ? n / 100 : n;
  return formatGel(gel);
}

export default function PaymentSuccess() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const pending = useMemo(() => readPendingPayment(), []);

  const status = (param(params, ["order_status", "response_status", "status"]) ?? "").toLowerCase();
  const failed = ["declined", "expired", "failed", "failure", "reversed"].includes(status);
  const approved = ["approved", "success", "paid", "completed"].includes(status);
  const orderId = param(params, ["order_id", "payment_id"]);
  const when = param(params, ["order_time", "payment_date", "tran_date"]);
  const card = param(params, ["masked_card", "card_number", "rectoken_mask"]);
  const amount = money(param(params, ["amount", "actual_amount"]), pending?.price);
  const hasReceipt = Boolean(amount || orderId || when || card || pending);

  const title = failed ? "გადახდა ვერ შესრულდა" : approved || pending ? "ყიდვა წარმატებით" : "გადახდის შედეგი";
  const subtitle = pending?.kind === "package" && pending.car
    ? [pending.car, pending.washes != null ? `${pending.washes} რეცხვა` : "", pending.months != null ? `${pending.months} თვე` : ""].filter(Boolean).join(" · ")
    : pending?.kind === "points" && pending.points
      ? `${pending.points} ქულა`
      : "";

  return (
    <div className="v4-pay">
      <button type="button" className="v4-ib x" aria-label="დახურვა" onClick={() => navigate("/")}>
        <X size={21} />
      </button>
      {(approved || (pending && !failed) || failed) && (
        <div className="v4-check" aria-hidden>
          <div className="c" style={{ width: 270, height: 270, background: failed ? "#F8E8E6" : "#F3F9E6" }} />
          <div className="c" style={{ width: 200, height: 200, background: failed ? "#F3D4D1" : "#E5F3C6" }} />
          <div className="c ok" style={failed ? { background: "var(--ap-error)", color: "#fff", boxShadow: "none" } : undefined}>
            {failed ? <X size={58} strokeWidth={3} /> : <Check size={58} strokeWidth={3} />}
          </div>
        </div>
      )}
      <div style={{ textAlign: "center", marginTop: approved || pending || failed ? 0 : 120 }}>
        <h1 className="ap-title">{title}</h1>
        {subtitle && <p className="ap-sub">{subtitle}</p>}
        {!hasReceipt && !failed && <p className="v4-empty">გადახდის დეტალები არ მოსულა. პაკეტი ან ქულები ბალანსზე გამოჩნდება დადასტურების შემდეგ.</p>}
      </div>
      {hasReceipt && !failed && (
        <div className="v4-receipt">
          {amount && (
            <div className="rr"><span>გადახდილი თანხა</span><b>{amount}</b></div>
          )}
          {card && (
            <div className="rr"><span>ბარათი</span><b>{card}</b></div>
          )}
          {pending?.renewal != null && (
            <div className="rr"><span>ავტომატური განახლება</span><b>{pending.renewal ? "ჩართულია" : "გამორთულია"}</b></div>
          )}
          {when && (
            <div className="rr"><span>თარიღი</span><b>{when}</b></div>
          )}
          <div className="rr"><span>გადახდის სისტემა</span><b>TBC</b></div>
          {orderId && (
            <div className="rr"><span>შეკვეთის №</span><b>{orderId}</b></div>
          )}
        </div>
      )}
      <button type="button" className="ap-btn" style={{ marginTop: 22 }} onClick={() => navigate("/")}>
        მთავარზე დაბრუნება
      </button>
    </div>
  );
}
