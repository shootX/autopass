import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import App from "./App";
import { invalidatePackagesCache } from "./hooks/useActivePackages";
import { invalidateCarsCache } from "./hooks/useFetchCars";
import { store } from "./store";
import { resetSession, setRole, setSession } from "./store/userSlice";
import { clearAccessToken, saveAccessToken } from "@/lib/accessToken";

vi.mock("./components/BranchMap", () => ({
  BranchMap: () => null,
}));

const translations = {
  Authentication: {
    greeting: { title: "შესვლა", subtitle: "ანგარიშში შესვლა" },
    labels: { phone: "ტელეფონი", password: "პაროლი" },
    placeholders: { phone: "500500555", password: "პაროლი" },
    prefix: "+995",
    forgotPassword: "პაროლი დამავიწყდა",
    buttons: { signIn: "შესვლა", signUp: "რეგისტრაცია" },
    signupPrompt: "არ გაქვს ანგარიში?",
    logoAlt: "autopass",
  },
  Home: { greeting: "გამარჯობა" },
  Help: { title: "დახმარება", backAlt: "უკან" },
  NotFound: { title: "გვერდი ვერ მოიძებნა", home: "მთავარზე დაბრუნება" },
  Sandbox: {
    title: "სატესტო გადახდა",
    body: "სატესტო გადახდა ამ ბილდში ჩართულია. ბარათის მონაცემები აქ არ შეიყვანება.",
  },
};

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

function requestUrl(input: RequestInfo | URL): string {
  if (typeof input === "string") return input;
  if (input instanceof URL) return input.toString();
  return input.url;
}

function installFetch(): void {
  vi.stubGlobal(
    "fetch",
    vi.fn(async (input: RequestInfo | URL): Promise<Response> => {
      const url = requestUrl(input);
      if (url.includes("/lang/")) return json(translations);
      if (url.includes("/device/register")) return json({ token: "device-token" });
      if (url.includes("/me")) {
        return json({
          success: true,
          user: {
            id: 1,
            name: "ნინო",
            surname: "ბერიძე",
            role: 0,
            phone: "555112233",
            email: "nino@example.com",
            points: 12,
            referrals_count: 0,
          },
        });
      }
      if (url.includes("/mycars")) {
        return json({
          cars: [
            {
              id: 7,
              plate: "AA001BB",
              model: { name: "Camry", type: "sedan", brand: { name: "Toyota" } },
            },
          ],
        });
      }
      if (url.includes("/packages/my")) return json({ success: true, packages: [] });
      if (url.includes("/myappointments")) return json({ appointments: [] });
      if (url.includes("/branches")) return json({ branches: [] });
      if (url.includes("/shop/home")) return json({ vouchers: [], tickets: [] });
      if (url.includes("/promo")) return json({ success: false });
      return json({});
    }),
  );
}

beforeEach(() => {
  clearAccessToken();
  localStorage.clear();
  sessionStorage.clear();
  invalidateCarsCache();
  invalidatePackagesCache();
  store.dispatch(resetSession());
  sessionStorage.setItem("gewash_lang:ka", JSON.stringify(translations));
  localStorage.setItem("autopass.onboarding.v4", "1");
  installFetch();
});

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
  window.history.pushState({}, "", "/");
});

describe("app router", () => {
  it("renders the home page", async () => {
    saveAccessToken("customer-token");
    store.dispatch(setRole("customer"));
    store.dispatch(setSession("ready"));
    window.history.pushState({}, "", "/");

    render(<App />);

    expect((await screen.findAllByText("AA001BB", {}, { timeout: 8000 })).length).toBeGreaterThan(0);
    expect(screen.getAllByText("დამატება").length).toBeGreaterThan(0);
    expect(screen.getAllByText("სწრაფი ჩაწერა").length).toBeGreaterThan(0);
    expect(document.body.textContent?.trim().length).toBeGreaterThan(0);
  }, 20000);

  it("renders the login page at /auth", async () => {
    window.history.pushState({}, "", "/auth");

    render(<App />);

    expect(await screen.findByRole("heading", { name: "შესვლა" }, { timeout: 8000 })).toBeTruthy();
    expect(screen.getByPlaceholderText("500500555")).toBeTruthy();
    expect(screen.getByRole("button", { name: "შესვლა" })).toBeTruthy();
    expect(document.body.textContent?.trim().length).toBeGreaterThan(0);
  }, 20000);

  it("renders public help without sending the visitor to sign-in", async () => {
    window.history.pushState({}, "", "/help");

    render(<App />);

    expect(await screen.findByText("დახმარება", {}, { timeout: 8000 })).toBeTruthy();
    expect(screen.queryByRole("heading", { name: "შესვლა" })).toBeNull();
    expect(screen.queryByPlaceholderText("500500555")).toBeNull();
  }, 20000);

  it("does not expose the payment sandbox unless test payment is configured", async () => {
    window.history.pushState({}, "", "/payment/sandbox");

    render(<App />);

    expect(await screen.findByRole("heading", { name: "გვერდი ვერ მოიძებნა" }, { timeout: 8000 })).toBeTruthy();
    expect(screen.queryByRole("heading", { name: "შესვლა" })).toBeNull();
    expect(screen.queryByRole("heading", { name: "სატესტო გადახდა" })).toBeNull();
    expect(document.body.textContent ?? "").not.toMatch(/CVV|4111/);
  }, 20000);

  it("does not copy a device token into an iframe URL", async () => {
    const frame = document.createElement("iframe");
    frame.src = "https://evil.example/embed";
    document.body.appendChild(frame);
    saveAccessToken("customer-token");
    store.dispatch(setRole("customer"));
    store.dispatch(setSession("ready"));
    window.history.pushState({}, "", "/");

    render(<App />);

    expect((await screen.findAllByText("AA001BB", {}, { timeout: 8000 })).length).toBeGreaterThan(0);
    expect(frame.src).toBe("https://evil.example/embed");
    expect(frame.src).not.toContain("device-token");
    expect(frame.src).not.toContain("token=");
    frame.remove();
  }, 20000);
});
