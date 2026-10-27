import { cleanup, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import QRPage from "./QRPage";
import { saveAccessToken } from "@/lib/accessToken";

const mock = vi.hoisted(() => ({ fetch: vi.fn(), cars: [] as unknown[], packages: [] as unknown[] }));
vi.mock("@/utils/customFetch", () => ({ customFetch: mock.fetch }));
vi.mock("@/hooks/useTranslation", () => ({ useTranslation: () => (key: string) => key }));
vi.mock("@/components/Header", () => ({ default: () => null }));
vi.mock("@/hooks/useFetchCars", () => ({ useFetchCars: () => ({ cars: mock.cars, loading: false, error: "" }) }));
vi.mock("@/hooks/useActivePackages", () => ({ useMyPackages: () => ({ packages: mock.packages, isLoading: false, error: null }) }));

const car = (status: string) => ({ id: 5, plate: "AA123BB", year: 2016, type: "Unknown", model: "Avensis", brand: "Toyota", image: null,
  body: { status, types: [], incomplete: false }, body_status: status });

beforeEach(() => { mock.fetch.mockReset(); mock.packages = []; saveAccessToken("qr-token"); });
afterEach(() => { cleanup(); localStorage.clear(); });

it("sends a car whose body type waits to the body pick, not to the packages", () => {
  mock.cars = [car("choose")];
  render(<MemoryRouter><QRPage /></MemoryRouter>);
  expect(screen.getByText("CarBody.qrBlocked")).toBeTruthy();
  expect(screen.getByRole("button", { name: "CarBody.open" })).toBeTruthy();
  expect(screen.queryByText("შეარჩიე პაკეტი")).toBeNull();
});

it("shows no QR when the server refuses it for an unconfirmed body type", async () => {
  mock.cars = [car("unknown")];
  mock.packages = [{ id: 9, car: { id: 5, plate: "AA123BB" }, number_of_washes: 4, used_washes: 0, end_date: "2099-01-01" }];
  mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: false, error_code: "body_unconfirmed" }), { status: 422 }));
  render(<MemoryRouter><QRPage /></MemoryRouter>);
  expect(await screen.findByText("CarBody.qrBlocked")).toBeTruthy();
  expect(screen.queryByText("QRPage.overlay.loading")).toBeNull();
  expect(mock.fetch.mock.calls[0][0]).toContain("/packages/9/qr");
});

it("keeps the QR for a confirmed car", async () => {
  mock.cars = [car("confirmed")];
  mock.packages = [{ id: 9, car: { id: 5, plate: "AA123BB" }, number_of_washes: 4, used_washes: 0, end_date: "2099-01-01" }];
  mock.fetch.mockResolvedValue(new Response("<svg/>", { status: 200, headers: { "Content-Type": "image/svg+xml" } }));
  const createObjectURL = vi.fn(() => "blob:qr");
  vi.stubGlobal("URL", Object.assign(URL, { createObjectURL }));
  render(<MemoryRouter><QRPage /></MemoryRouter>);
  await waitFor(() => expect(screen.getByAltText("QR")).toBeTruthy());
  expect(screen.queryByText("CarBody.qrBlocked")).toBeNull();
});
