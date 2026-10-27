import { customFetch } from "@/utils/customFetch";
import { readAccessToken } from "@/lib/accessToken";
import { readCar, record } from "@/lib/carResult";
import { readBodyTypes, type BodyTypeOption } from "@/lib/carBody";
import type { Car } from "@/store/carSlice";

type BodyResult = { car: Car | null; error: string; message: string; types: BodyTypeOption[] };

async function send(path: string, payload?: object): Promise<BodyResult> {
  const response = await customFetch(`${import.meta.env.VITE_API_URL}${path}`, {
    method: "POST",
    headers: { Authorization: `Bearer ${readAccessToken()}`, "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(payload ?? {}),
  });
  const body = record(await response.json().catch(() => null));
  const ok = response.ok && body?.success === true;
  return {
    car: ok ? readCar(body?.car) : null,
    error: ok ? "" : typeof body?.error === "string" ? body.error : "failed",
    message: typeof body?.message === "string" ? body.message : "",
    types: readBodyTypes(body?.body_types),
  };
}

/** POST /mycars/{id}/body: the server takes the pick only when it is one of geoapi.ge's types for the car. */
export function chooseCarBody(carId: number, carType: string): Promise<BodyResult> {
  return send(`/mycars/${carId}/body`, { car_type: carType });
}

/** POST /mycars/{id}/body/check: asks geoapi.ge again about a car whose type it could not tell. */
export function recheckCarBody(carId: number): Promise<BodyResult> {
  return send(`/mycars/${carId}/body/check`);
}
