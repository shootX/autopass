import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import Authentication from "./Authentication";
import { clearAccessToken, readAccessToken } from "@/lib/accessToken";

const mock = vi.hoisted(() => ({ fetch: vi.fn(), dispatch: vi.fn() }));
vi.mock("react-redux", () => ({ useDispatch: () => mock.dispatch }));
vi.mock("@/lib/fetchUserData", () => ({ fetchUserData: vi.fn(async () => 0) }));
vi.mock("@/hooks/useTranslation", () => ({ useTranslation: () => (key: string) => key }));

function submit() {
  render(<MemoryRouter><Authentication /></MemoryRouter>);
  fireEvent.change(screen.getByPlaceholderText("Authentication.placeholders.phone"), { target: { value: "555123456" } });
  fireEvent.change(screen.getByPlaceholderText("Authentication.placeholders.password"), { target: { value: "fixture-password" } });
  fireEvent.click(screen.getByRole("button", { name: "Authentication.buttons.signIn" }));
}

beforeEach(() => {
  mock.fetch.mockReset();
  vi.stubGlobal("fetch", mock.fetch);
  vi.stubEnv("VITE_API_URL", "https://api.example.test/api");
});
afterEach(() => { clearAccessToken(); cleanup(); localStorage.clear(); vi.restoreAllMocks(); vi.unstubAllGlobals(); vi.unstubAllEnvs(); });

describe("authentication failure and credential privacy", () => {
  it("shows a localized message when the connection fails", async () => {
    vi.spyOn(console, "error").mockImplementation(() => undefined);
    mock.fetch.mockRejectedValue(new TypeError("Connection unavailable"));
    submit();
    expect(await screen.findByText("Authentication.errors.connectionFailed")).toBeTruthy();
    expect((screen.getByRole("button", { name: "Authentication.buttons.signIn" }) as HTMLButtonElement).disabled).toBe(false);
  });

  it("stores a successful token without logging the credential response", async () => {
    const log = vi.spyOn(console, "log").mockImplementation(() => undefined);
    mock.fetch.mockResolvedValue(new Response(JSON.stringify({ access_token: "fixture-access-token", expires_in: 3600 })));
    submit();
    await waitFor(() => expect(readAccessToken()).toBe("fixture-access-token"));
    const init = mock.fetch.mock.calls[0]?.[1] as RequestInit;
    expect(init.credentials).toBe("include");
    expect(localStorage.getItem("access_token")).toBeNull();
    expect(log.mock.calls.some((args) => JSON.stringify(args).includes("fixture-access-token"))).toBe(false);
  });
});
