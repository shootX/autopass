import { useEffect, useRef, useState } from "react";
import { fetchRedeemedVouchers, type RedeemedVoucher } from "../lib/historyApi";
import { formatCodeForDisplay } from "../lib/voucherApi";
import Spinner from "./Spinner";

type Props = {
  /** Changes after each redemption, so the list reloads. */
  refreshKey: number;
  onFlowError: (err: unknown) => boolean;
};

function formatDate(iso: string | null): string {
  if (!iso) return "—";
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? "—" : d.toLocaleString();
}

export default function VoucherHistory({ refreshKey, onFlowError }: Props) {
  const [rows, setRows] = useState<RedeemedVoucher[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const flowError = useRef(onFlowError);

  useEffect(() => {
    flowError.current = onFlowError;
  }, [onFlowError]);

  useEffect(() => {
    let alive = true;
    fetchRedeemedVouchers()
      .then((list) => {
        if (!alive) return;
        setRows(list);
        setError(null);
      })
      .catch((err: unknown) => {
        if (!alive || flowError.current(err)) return;
        setError(err instanceof Error ? err.message : "Could not load history");
      });
    return () => {
      alive = false;
    };
  }, [refreshKey]);

  return (
    <section className="cv-card cv-history" aria-label="Redeemed vouchers">
      <h2 className="cv-history__title">Redeemed vouchers</h2>
      {error ? (
        <div className="cv-error-banner" role="alert">
          {error}
        </div>
      ) : rows === null ? (
        <Spinner />
      ) : rows.length === 0 ? (
        <p className="cv-history__empty">No vouchers redeemed yet.</p>
      ) : (
        <ul className="cv-history__list">
          {rows.map((row) => (
            <li key={row.code} className="cv-history__row">
              <span className="cv-history__code">{formatCodeForDisplay(row.code)}</span>
              <span>{row.voucher ?? "—"}</span>
              <span>{row.client ?? "—"}</span>
              <span className="cv-history__date">{formatDate(row.usedAt)}</span>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
