import { useState } from "react";
import { useTranslation } from "@/hooks/useTranslation";
import type { CarBody } from "@/lib/carBody";
import { chooseCarBody, recheckCarBody } from "@/lib/carBodyApi";

/**
 * A car whose body type is not confirmed: the driver picks one of the types geoapi.ge named (the server checks
 * the pick), or asks geoapi.ge again when it could not tell. Packages and QR for this car wait until then.
 */
export function CarBodyPrompt({ carId, body, onSaved }: { carId: number; body: CarBody; onSaved: () => void }) {
  const t = useTranslation();
  const [pick, setPick] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  if (body.status === "confirmed") return null;
  const choosing = body.status === "choose" && body.types.length > 0;

  const run = async (action: () => ReturnType<typeof chooseCarBody>) => {
    setBusy(true);
    setMessage("");
    try {
      const result = await action();
      setMessage(result.error || result.message);
      if (!result.error) onSaved();
    } catch {
      setMessage(t("CarBody.failed"));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="car-body-prompt">
      <b>{t(choosing ? "CarBody.choose" : "CarBody.unknown")}</b>
      <p>{t("CarBody.blocked")}</p>
      {choosing ? (
        <>
          <label htmlFor={`car-body-${carId}`}>{t("CarBody.label")}</label>
          <select id={`car-body-${carId}`} value={pick} onChange={(event) => { setPick(event.target.value); setMessage(""); }}>
            <option value="">{t("CarBody.placeholder")}</option>
            {body.types.map((item) => <option key={item.key} value={item.key}>{item.name}</option>)}
          </select>
          {body.incomplete && <p>{t("CarBody.incomplete")}</p>}
          <button type="button" disabled={!pick || busy} onClick={() => run(() => chooseCarBody(carId, pick))}>
            {t("CarBody.save")}
          </button>
        </>
      ) : (
        <button type="button" disabled={busy} onClick={() => run(() => recheckCarBody(carId))}>
          {t("CarBody.retry")}
        </button>
      )}
      {message && <p role="alert">{message}</p>}
    </div>
  );
}
