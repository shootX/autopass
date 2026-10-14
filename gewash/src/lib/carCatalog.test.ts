import { describe, expect, it } from "vitest";
import {
  catalogKey,
  catalogWriteIds,
  myCarAddBody,
  myCarEditBody,
  parseCatalogItem,
  parseCatalogList,
  parseYears,
} from "./carCatalog";

const camry = { id: 7, name: "Camry", source: "carapi" as const };
const toyota = { id: 501, name: "Toyota", source: "carapi" as const };
const niva = { id: 7, name: "Niva", source: "local" as const };
const lada = { id: 4, name: "Lada", source: "local" as const };

describe("car catalog ids", () => {
  it("sends a CarAPI model id with source carapi, not as a local id", () => {
    expect(myCarAddBody({ plate: "AA123BB", brand: toyota, model: camry, year: 2019 })).toEqual({
      plate: "AA123BB",
      source: "carapi",
      model_id: 7,
      brand_id: 501,
      year: 2019,
    });
  });

  it("sends the same numeric id as local when the pick came from the local catalog", () => {
    expect(myCarAddBody({ plate: "AA125BB", brand: lada, model: niva })).toEqual({
      plate: "AA125BB",
      source: "local",
      model_id: 7,
      brand_id: 4,
    });
  });

  it("refuses a model whose source does not match the brand", () => {
    expect(myCarAddBody({ plate: "AA123BB", brand: lada, model: camry })).toBeNull();
  });

  it("drops catalog rows that have an id but no source", () => {
    expect(parseCatalogItem({ id: 7, name: "Camry" })).toBeNull();
    expect(parseCatalogList([{ id: 7, name: "Camry" }, camry])).toEqual([camry]);
  });

  it("keeps two catalogs with the same id distinct", () => {
    expect(catalogKey(camry)).toBe("carapi:7");
    expect(catalogKey(niva)).toBe("local:7");
    expect(catalogKey(camry)).not.toBe(catalogKey(niva));
  });

  it("does not turn a saved local row id into a CarAPI id", () => {
    expect(
      catalogWriteIds({
        id: 99,
        source: "carapi",
        external_model_id: 7,
        brand: { id: 3, source: "carapi", external_brand_id: 501 },
      }),
    ).toEqual({ source: "carapi", model_id: 7, brand_id: 501 });
  });

  it("uses the local row id only when source is local", () => {
    expect(catalogWriteIds({ id: 7, source: "local", brand: { id: 4, source: "local" } })).toEqual({
      source: "local",
      model_id: 7,
      brand_id: 4,
    });
    expect(catalogWriteIds({ id: 7 })).toBeNull();
    expect(catalogWriteIds({ id: 99, source: "carapi", external_model_id: null })).toBeNull();
  });

  it("edits plate and year without resending an unqualified model id", () => {
    const body = myCarEditBody({ plate: "BB333BB", year: 2021, includeYear: true });
    expect(body).toEqual({ plate: "BB333BB", year: 2021 });
    expect(body).not.toHaveProperty("model_id");
    expect(body).not.toHaveProperty("source");
  });

  it("clears year when the picker is emptied", () => {
    expect(myCarEditBody({ plate: "BB333BB", year: null, includeYear: true })).toEqual({
      plate: "BB333BB",
      year: null,
    });
  });

  it("reads the years list from GET /api/cars/years", () => {
    expect(parseYears({ success: true, years: [2027, 2026, 1950] })).toEqual([2027, 2026, 1950]);
    expect(parseYears({ years: ["2020"] })).toEqual([]);
  });
});
