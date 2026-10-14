import { configureStore } from "@reduxjs/toolkit";
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { Provider } from "react-redux";
import { MemoryRouter } from "react-router-dom";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import AddCar from "./AddCar";
import carReducer, { setBrand, setModel } from "@/store/carSlice";

const mock = vi.hoisted(() => ({ fetch: vi.fn() }));
vi.mock("@/utils/customFetch", () => ({ customFetch: mock.fetch }));
vi.mock("@/hooks/useCarBrands", () => ({ useCarBrands: () => ({ brands: [], loading: false, error: false }) }));
vi.mock("@/hooks/useCarYears", () => ({ useCarYears: () => ({ years: [2020], source: "catalog", loading: false, failed: false, retry: vi.fn() }) }));
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
  localStorage.setItem("access_token", "review-token");
  mock.fetch.mockReset();
});
afterEach(() => { cleanup(); localStorage.clear(); });

describe("car creation integration", () => {
  it("sends the selected catalog identity and year and keeps the server car id", async () => {
    mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: true, car: {
      id: 12, plate: "AA123BB", year: 2020, model: { id: 3, name: "Remote model", type: "sedan", brand: { name: "Remote brand" } },
    } }), { status: 200 }));
    const store = renderSelectedCar();
    fireEvent.change(screen.getByLabelText("AddCar.labels.year"), { target: { value: "2020" } });
    fireEvent.click(screen.getByRole("button", { name: "AddCar.buttons.add" }));
    await waitFor(() => expect(mock.fetch).toHaveBeenCalledTimes(1));
    const init = mock.fetch.mock.calls[0][1] as RequestInit;
    const body: unknown = JSON.parse(String(init.body));
    expect(body).toMatchObject({ source: "carapi", brand_id: 901, model_id: 7, year: 2020, plate: "AA123BB" });
    await waitFor(() => expect(store.getState().car.cars[0]?.id).toBe(12));
    expect(store.getState().car.cars[0]?.year).toBe(2020);
  });

  it("requires a year before submitting a new car", () => {
    renderSelectedCar();
    expect((screen.getByRole("button", { name: "AddCar.buttons.add" }) as HTMLButtonElement).disabled).toBe(true);
    expect(mock.fetch).not.toHaveBeenCalled();
  });

  it("does not label a catalog validation failure as a duplicate plate", async () => {
    mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: false, error: "catalog invalid" }), { status: 422 }));
    renderSelectedCar();
    fireEvent.change(screen.getByLabelText("AddCar.labels.year"), { target: { value: "2020" } });
    fireEvent.click(screen.getByRole("button", { name: "AddCar.buttons.add" }));
    expect(await screen.findByText("AddCar.errors.saveFailed")).toBeTruthy();
    expect(screen.queryByText("AddCar.errors.plateExists")).toBeNull();
  });

  it("shows the duplicate plate message only for that server error", async () => {
    mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: false, error_code: "plate_exists" }), { status: 400 }));
    renderSelectedCar();
    fireEvent.change(screen.getByLabelText("AddCar.labels.year"), { target: { value: "2020" } });
    fireEvent.click(screen.getByRole("button", { name: "AddCar.buttons.add" }));
    expect(await screen.findByText("AddCar.errors.plateExists")).toBeTruthy();
  });
});

