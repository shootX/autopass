import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { CarBodyPrompt } from "./CarBodyPrompt";
import { saveAccessToken } from "@/lib/accessToken";
import { bodyConfirmed, carBodyType, readBody } from "@/lib/carBody";
import { readCar } from "@/lib/carResult";

const mock = vi.hoisted(() => ({ fetch: vi.fn() }));
vi.mock("@/utils/customFetch", () => ({ customFetch: mock.fetch }));
vi.mock("@/hooks/useTranslation", () => ({ useTranslation: () => (key: string) => key }));

const choose = { status: "choose" as const, types: [{ key: "sedan", name: "Sedan" }, { key: "wagon", name: "Wagon" }], incomplete: false };

beforeEach(() => { mock.fetch.mockReset(); saveAccessToken("body-token"); });
afterEach(() => { cleanup(); localStorage.clear(); });

describe("car body status", () => {
  it("reads the status and never takes the model's type for a car whose type waits", () => {
    const car = { id: 5, plate: "AA123BB", car_type: null, body_status: "choose", body_types: choose.types, body_incomplete: true,
      model: { name: "Avensis", type: "sedan", brand: { name: "Toyota" } } };
    expect(readBody(car)).toEqual({ ...choose, incomplete: true });
    expect(carBodyType(car, "sedan")).toBe("");
    expect(readCar(car)).toMatchObject({ car_type: null, body_status: "choose", model: { type: "" } });
    expect(bodyConfirmed({ body_status: "unknown" })).toBe(false);
    // A server from before the body status: nothing is blocked and the model's type stays as it was.
    expect(readBody({ id: 1 }).status).toBe("confirmed");
    expect(carBodyType({ id: 1 }, "suv")).toBe("suv");
  });

  it("offers only the server's types and sends the pick", async () => {
    mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: true, car: { id: 5, plate: "AA123BB", car_type: "wagon", body_status: "confirmed" } }), { status: 200 }));
    const saved = vi.fn();
    render(<CarBodyPrompt carId={5} body={choose} onSaved={saved} />);
    const select = screen.getByLabelText("CarBody.label") as HTMLSelectElement;
    expect([...select.options].map(option => option.value)).toEqual(["", "sedan", "wagon"]);
    expect((screen.getByRole("button", { name: "CarBody.save" }) as HTMLButtonElement).disabled).toBe(true);
    fireEvent.change(select, { target: { value: "wagon" } });
    fireEvent.click(screen.getByRole("button", { name: "CarBody.save" }));
    await waitFor(() => expect(saved).toHaveBeenCalledTimes(1));
    expect(mock.fetch.mock.calls[0][0]).toContain("/mycars/5/body");
    expect(JSON.parse(String((mock.fetch.mock.calls[0][1] as RequestInit).body))).toEqual({ car_type: "wagon" });
  });

  it("shows the server's refusal and keeps the car waiting", async () => {
    mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: false, error: "not one of them", error_code: "body_type_invalid" }), { status: 422 }));
    const saved = vi.fn();
    render(<CarBodyPrompt carId={5} body={choose} onSaved={saved} />);
    fireEvent.change(screen.getByLabelText("CarBody.label"), { target: { value: "sedan" } });
    fireEvent.click(screen.getByRole("button", { name: "CarBody.save" }));
    expect(await screen.findByText("not one of them")).toBeTruthy();
    expect(saved).not.toHaveBeenCalled();
  });

  it("asks geoapi.ge again for an unknown type and shows what the server says", async () => {
    mock.fetch.mockResolvedValue(new Response(JSON.stringify({ success: true, car: { id: 6, plate: "AA124BB", body_status: "unknown" }, message: "catalog not answering" }), { status: 200 }));
    render(<CarBodyPrompt carId={6} body={{ status: "unknown", types: [], incomplete: false }} onSaved={vi.fn()} />);
    expect(screen.queryByLabelText("CarBody.label")).toBeNull();
    fireEvent.click(screen.getByRole("button", { name: "CarBody.retry" }));
    expect(await screen.findByText("catalog not answering")).toBeTruthy();
    expect(mock.fetch.mock.calls[0][0]).toContain("/mycars/6/body/check");
  });

  it("shows nothing for a confirmed car", () => {
    const { container } = render(<CarBodyPrompt carId={7} body={{ status: "confirmed", types: [], incomplete: false }} onSaved={vi.fn()} />);
    expect(container.innerHTML).toBe("");
  });
});
