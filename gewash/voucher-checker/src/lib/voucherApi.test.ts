import { afterEach, describe, expect, it, vi } from "vitest";
import { confirmVoucherUse, checkVoucher, opaqueTempCode } from "./voucherApi";
import { PartnerFlowError } from "./partnerGate";
import { clearToken, setToken } from "./session";

const ID = "0123456789abcdef0123456789abcdef";

function jsonResponse(body: unknown, status: number) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

afterEach(() => {
  clearToken();
  vi.unstubAllGlobals();
  vi.unstubAllEnvs();
  vi.restoreAllMocks();
});

function installStorage() {
  const bag = new Map<string, string>([["voucher_checker_access_token", "partner-token"]]);
  const storage = {
    get length() {
      return bag.size;
    },
    clear: () => bag.clear(),
    getItem: (key: string) => bag.get(key) ?? null,
    setItem: (key: string, value: string) => {
      bag.set(key, value);
    },
    removeItem: (key: string) => {
      bag.delete(key);
    },
    key: (index: number) => [...bag.keys()][index] ?? null,
  };
  vi.stubGlobal("localStorage", storage);
  return storage;
}

describe("voucher temp_code", () => {
  it("keeps only a 32-character string", () => {
    expect(opaqueTempCode(ID)).toBe(ID);
    expect(opaqueTempCode(7)).toBeNull();
    expect(opaqueTempCode("123456")).toBeNull();
  });

  it("posts temp_code as a string and does not store the SMS code", async () => {
    const storage = installStorage();
    setToken("partner-token");
    const fetchMock = vi.fn(async () => jsonResponse({ success: true }, 200));
    vi.stubGlobal("fetch", fetchMock);

    await confirmVoucherUse(ID, "123456");

    const calls = fetchMock.mock.calls as unknown as [string, RequestInit][];
    const body = JSON.parse(String(calls[0]?.[1]?.body));
    expect(body).toEqual({ temp_code: ID, code: "123456" });
    expect(typeof body.temp_code).toBe("string");
    expect(storage.getItem("voucher_checker_access_token")).toBeNull();
    expect(JSON.stringify(storage)).not.toContain("123456");
  });

  it("rejects a numeric temp code before calling the API", async () => {
    installStorage();
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);
    await expect(confirmVoucherUse("42", "123456")).rejects.toThrow(/Session expired/);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("keeps the code entry open when delivery is unknown", async () => {
    installStorage();
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => jsonResponse({ success: false, delivery: "unknown", temp_code: ID, message: "unknown" }, 503)),
    );
    const result = await checkVoucher("AB12CD34");
    expect(result.ok).toBe(true);
    expect(result.tempCode).toBe(ID);
  });

  it("does not use the voucher mock when the build is production", async () => {
    installStorage();
    vi.stubEnv("PROD", true);
    vi.stubEnv("VITE_MOCK_VOUCHER_CHECK", "true");
    const fetchMock = vi.fn(async () => jsonResponse({ success: true, temp_code: ID }, 200));
    vi.stubGlobal("fetch", fetchMock);

    const result = await checkVoucher("AB12CD34");

    expect(fetchMock).toHaveBeenCalled();
    expect(result.payload.message).not.toBe("OK (mock)");
  });

  it("does not hand back a code when delivery failed", async () => {
    installStorage();
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => jsonResponse({ success: false, delivery: "failed", message: "not sent" }, 422)),
    );
    const result = await checkVoucher("AB12CD34");
    expect(result.ok).toBe(false);
    expect(result.tempCode).toBeUndefined();
  });

  it("logs the partner out on 401 and blocks on must_change_password", async () => {
    installStorage();
    setToken("partner-token");
    vi.stubGlobal("fetch", vi.fn(async () => jsonResponse({ error: "Unauthorized" }, 401)));
    await expect(checkVoucher("AB12CD34")).rejects.toBeInstanceOf(PartnerFlowError);

    vi.stubGlobal(
      "fetch",
      vi.fn(async () => jsonResponse({ success: false, error: "Password change required", must_change_password: true }, 403)),
    );
    await expect(checkVoucher("AB12CD34")).rejects.toMatchObject({ kind: "must_change" });
  });
});
