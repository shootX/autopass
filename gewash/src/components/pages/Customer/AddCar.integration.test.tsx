import { configureStore } from "@reduxjs/toolkit";
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { Provider } from "react-redux";
import { MemoryRouter } from "react-router-dom";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import AddCar from "./AddCar";
import carReducer, { setBrand, setModel } from "@/store/carSlice";
import { saveAccessToken } from "@/lib/accessToken";

const mock = vi.hoisted(() => ({ fetch: vi.fn(), bodyTypes: [] as { key: string; name: string }[], unknown: false }));
vi.mock("@/utils/customFetch", () => ({ customFetch: mock.fetch }));
vi.mock("@/hooks/useCarBrands", () => ({ useCarBrands: () => ({ brands: [], loading: false, error: false }) }));
vi.mock("@/hooks/useCarYears", () => ({ useCarYears: () => ({ years: [2020], source: "catalog", loading: false, failed: false, retry: vi.fn() }) }));
vi.mock("@/hooks/useCarBodyTypes", () => ({ useCarBodyTypes: () => ({
  types: mock.bodyTypes, choose: mock.bodyTypes.length > 1, unknown: mock.unknown, incomplete: false, loading: false, failed: false, retry: vi.fn(),
}) }));
vi.mock("@/hooks/useTranslation", () => ({ useTranslation: () => (key: string) => key }));
vi.mock("@/components/Header", () => ({ default: () => null }));

function renderSelectedCar() {
  const store = configureStore({ reducer: { car: carReducer } });
  store.dispatch(setBrand({ id: 901, name: "Remote brand", source: "carapi" }));
  store.dispatch(setModel({ id: 7, name: "Remote model", source: "carapi" }));
  render(<Provider store={store}><MemoryRouter><AddCar /></MemoryRouter></Provider>);
  fireEvent.change(screen.getByPlaceholderText("AddCar.placeholders.plate"), { target: { value: "AA123BB" } });
  return store;
}

beforeEach(() => {
  saveAccessToken("review-token");
  mock.fetch.mockReset();
  mock.bodyTypes = [];
  mock.unknown = false;
});
afterEach(() => { cleanup(); localStorage.clear(); });

describe("car creation integration", () => {
  it("sends the selected catalog identity and year and keeps the server car id", async () => {
    mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: true, car: {
      id: 12, plate: "AA123BB", year: 2020, model: { id: 3, name: "Remote model", type: "sedan", brand: { name: "Remote brand" } },
    } }), { status: 200 }));
    const store = renderSelectedCar();
    fireEvent.change(screen.getByLabelText("AddCar.labels.year"), { target: { value: "2020" } });
    fireEvent.change(screen.getByLabelText("AddCar.labels.fuel"), { target: { value: "premium" } });
    fireEvent.click(screen.getByRole("button", { name: "AddCar.buttons.add" }));
    await waitFor(() => expect(mock.fetch).toHaveBeenCalledTimes(1));
    const init = mock.fetch.mock.calls[0][1] as RequestInit;
    const body: unknown = JSON.parse(String(init.body));
    expect(body).toMatchObject({ source: "carapi", brand_id: 901, model_id: 7, year: 2020, plate: "AA123BB", fuel_type: "premium" });
    await waitFor(() => expect(store.getState().car.cars[0]?.id).toBe(12));
    expect(store.getState().car.cars[0]?.year).toBe(2020);
  });

  it("offers the body types only when the year has several, and sends the pick", async () => {
    mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: true, car: {
      id: 13, plate: "AA123BB", year: 2020, car_type: "wagon", body_status: "confirmed", model: { id: 3, name: "Remote model", type: "sedan", brand: { name: "Remote brand" } },
    } }), { status: 200 }));
    mock.bodyTypes = [{ key: "sedan", name: "Sedan" }, { key: "wagon", name: "Wagon" }];
    renderSelectedCar();
    fireEvent.change(screen.getByLabelText("AddCar.labels.year"), { target: { value: "2020" } });
    fireEvent.change(screen.getByLabelText("AddCar.labels.fuel"), { target: { value: "premium" } });
    const options = [...(screen.getByLabelText("AddCar.labels.bodyType") as HTMLSelectElement).options].map(option => option.value);
    expect(options).toEqual(["", "sedan", "wagon"]);
    fireEvent.change(screen.getByLabelText("AddCar.labels.bodyType"), { target: { value: "wagon" } });
    fireEvent.click(screen.getByRole("button", { name: "AddCar.buttons.add" }));
    await waitFor(() => expect(mock.fetch).toHaveBeenCalledTimes(1));
    expect(JSON.parse(String((mock.fetch.mock.calls[0][1] as RequestInit).body))).toMatchObject({ car_type: "wagon", year: 2020 });
  });

  it("saves a car with several body types without a pick, and says its type still waits", async () => {
    mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: true, car: {
      id: 14, plate: "AA123BB", year: 2020, car_type: null, body_status: "choose", body_types: [{ key: "sedan", name: "Sedan" }, { key: "wagon", name: "Wagon" }],
      model: { id: 3, name: "Remote model", type: "sedan", brand: { name: "Remote brand" } },
    } }), { status: 200 }));
    mock.bodyTypes = [{ key: "sedan", name: "Sedan" }, { key: "wagon", name: "Wagon" }];
    const store = renderSelectedCar();
    fireEvent.change(screen.getByLabelText("AddCar.labels.year"), { target: { value: "2020" } });
    fireEvent.change(screen.getByLabelText("AddCar.labels.fuel"), { target: { value: "premium" } });
    fireEvent.click(screen.getByRole("button", { name: "AddCar.buttons.add" }));
    expect(await screen.findByText("AddCar.success.bodyPending")).toBeTruthy();
    expect(JSON.parse(String((mock.fetch.mock.calls[0][1] as RequestInit).body))).not.toHaveProperty("car_type");
    // The model's general type is not the car's type.
    expect(store.getState().car.cars[0]).toMatchObject({ car_type: null, body_status: "choose", model: { type: "" } });
  });

  it("says when geoapi.ge can't tell the body type, and still saves the car", () => {
    mock.unknown = true;
    renderSelectedCar();
    fireEvent.change(screen.getByLabelText("AddCar.labels.year"), { target: { value: "2020" } });
    fireEvent.change(screen.getByLabelText("AddCar.labels.fuel"), { target: { value: "premium" } });
    expect(screen.getByText("AddCar.bodyUnknown")).toBeTruthy();
    expect((screen.getByRole("button", { name: "AddCar.buttons.add" }) as HTMLButtonElement).disabled).toBe(false);
  });

  it("shows no body type field when the year has one type", () => {
    mock.bodyTypes = [{ key: "suv", name: "SUV" }];
    renderSelectedCar();
    fireEvent.change(screen.getByLabelText("AddCar.labels.year"), { target: { value: "2020" } });
    expect(screen.queryByLabelText("AddCar.labels.bodyType")).toBeNull();
  });

  it("requires a year before submitting a new car", () => {
    renderSelectedCar();
    expect((screen.getByRole("button", { name: "AddCar.buttons.add" }) as HTMLButtonElement).disabled).toBe(true);
    expect(mock.fetch).not.toHaveBeenCalled();
  });

  it("requires a fuel type before submitting a new car", () => {
    renderSelectedCar();
    fireEvent.change(screen.getByLabelText("AddCar.labels.year"), { target: { value: "2020" } });
    expect((screen.getByRole("button", { name: "AddCar.buttons.add" }) as HTMLButtonElement).disabled).toBe(true);
    fireEvent.change(screen.getByLabelText("AddCar.labels.fuel"), { target: { value: "diesel" } });
    expect((screen.getByRole("button", { name: "AddCar.buttons.add" }) as HTMLButtonElement).disabled).toBe(false);
  });

  it("does not label a catalog validation failure as a duplicate plate", async () => {
    mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: false, error: "catalog invalid" }), { status: 422 }));
    renderSelectedCar();
    fireEvent.change(screen.getByLabelText("AddCar.labels.year"), { target: { value: "2020" } });
    fireEvent.change(screen.getByLabelText("AddCar.labels.fuel"), { target: { value: "premium" } });
    fireEvent.click(screen.getByRole("button", { name: "AddCar.buttons.add" }));
    expect(await screen.findByText("AddCar.errors.saveFailed")).toBeTruthy();
    expect(screen.queryByText("AddCar.errors.plateExists")).toBeNull();
  });

  it("shows the duplicate plate message only for that server error", async () => {
    mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: false, error_code: "plate_exists" }), { status: 400 }));
    renderSelectedCar();
    fireEvent.change(screen.getByLabelText("AddCar.labels.year"), { target: { value: "2020" } });
    fireEvent.change(screen.getByLabelText("AddCar.labels.fuel"), { target: { value: "premium" } });
    fireEvent.click(screen.getByRole("button", { name: "AddCar.buttons.add" }));
    expect(await screen.findByText("AddCar.errors.plateExists")).toBeTruthy();
  });
});

