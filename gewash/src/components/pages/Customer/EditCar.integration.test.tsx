import { cleanup, render, screen } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import EditCar from "./EditCar";

const mock = vi.hoisted(() => ({ fetch: vi.fn() }));
vi.mock("@/utils/customFetch", () => ({ customFetch: mock.fetch }));
vi.mock("@/hooks/useTranslation", () => ({ useTranslation: () => (key: string) => key }));
vi.mock("@/components/Header", () => ({ default: () => null }));
beforeEach(() => { mock.fetch.mockReset(); localStorage.setItem("access_token", "review-only"); });
afterEach(() => { cleanup(); localStorage.clear(); });

it("loads an editable car and year directly without navigation state", async () => {
  mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: true, car: {
    id: 42, plate: "AA123BB", year: 2020,
    model: { id: 3, name: "Camry", type: "sedan", brand: { id: 5, name: "Toyota" } },
  } }), { status: 200 }));
  render(<MemoryRouter initialEntries={["/car/42"]}><Routes><Route path="/car/:carid" element={<EditCar />} /></Routes></MemoryRouter>);
  expect(await screen.findByText("Toyota")).toBeTruthy();
  expect(screen.getByText("Camry")).toBeTruthy();
  expect((screen.getByPlaceholderText("XX - XXX - XX") as HTMLInputElement).value).toBe("AA - 123 - BB");
  expect((screen.getByLabelText("AddCar.labels.year") as HTMLInputElement).value).toBe("2020");
  expect(mock.fetch.mock.calls[0][0]).toContain("/mycars/42");
});

it("shows a load failure instead of an editable empty car on a denied request", async () => {
  mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: false }), { status: 404 }));
  render(<MemoryRouter initialEntries={["/car/42"]}><Routes><Route path="/car/:carid" element={<EditCar />} /></Routes></MemoryRouter>);
  expect(await screen.findByText("EditCar.errors.loadFailed")).toBeTruthy();
  expect((screen.getByRole("button", { name: "EditCar.buttons.save" }) as HTMLButtonElement).disabled).toBe(true);
});

