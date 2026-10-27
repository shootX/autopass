/**
 * A car's body type as the server confirmed it. confirmed: packages and QR work. choose: geoapi.ge named several
 * types and the driver picks one of them. unknown: geoapi.ge could not tell (yet), so the driver can ask again.
 * Until the type is confirmed the server sells no package and gives no QR for the car; other cars keep working.
 */
export type BodyStatus = "confirmed" | "choose" | "unknown";
export type BodyTypeOption = { key: string; name: string };
export type CarBody = { status: BodyStatus; types: BodyTypeOption[]; incomplete: boolean };

export function readBodyTypes(value: unknown): BodyTypeOption[] {
  return Array.isArray(value) ? value.flatMap(item => {
    const row = item && typeof item === "object" ? item as Record<string, unknown> : null;
    return typeof row?.key === "string" && typeof row.name === "string" ? [{ key: row.key, name: row.name }] : [];
  }) : [];
}

/** The body fields of a car from the API. A server from before the body status has no field: nothing is blocked. */
export function readBody(car: Record<string, unknown> | null): CarBody {
  const status = car?.body_status;
  return {
    status: status === "choose" || status === "unknown" ? status : "confirmed",
    types: readBodyTypes(car?.body_types),
    incomplete: car?.body_incomplete === true,
  };
}

export function bodyConfirmed(car: { body_status?: string | null } | null | undefined): boolean {
  return !car?.body_status || car.body_status === "confirmed";
}

/** The car's own body type key: car_type once confirmed, never the model's general type. */
export function carBodyType(car: Record<string, unknown> | null, modelType: unknown): string {
  if (typeof car?.car_type === "string" && car.car_type) return car.car_type;
  if (car && "body_status" in car) return "";
  return typeof modelType === "string" ? modelType : "";
}
