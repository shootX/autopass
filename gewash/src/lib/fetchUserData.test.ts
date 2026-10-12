import { configureStore } from "@reduxjs/toolkit";
import { beforeEach, describe, expect, it, vi } from "vitest";
import userReducer from "@/store/userSlice";
import { customFetch } from "@/utils/customFetch";
import { refreshCurrentUser } from "@/hooks/useUser";
import { fetchUserData } from "./fetchUserData";

vi.mock("@/utils/customFetch", () => ({
  customFetch: vi.fn(),
}));

function makeStore() {
  return configureStore({ reducer: { user: userReducer } });
}

function meResponse(role: number, status = 200) {
  return new Response(
    JSON.stringify({
      success: true,
      user: { id: 7, role, name: "Nino", surname: "Beridze", phone: "995555", email: "n@example.com" },
    }),
    { status, headers: { "Content-Type": "application/json" } },
  );
}

beforeEach(() => {
  localStorage.clear();
  vi.mocked(customFetch).mockReset();
});

describe("GET /me session", () => {
  it("keeps the session pending until /me resolves, then records the manager role", async () => {
    localStorage.setItem("access_token", "ok");
    let finish: (response: Response) => void = () => {};
    vi.mocked(customFetch).mockReturnValue(new Promise((resolve) => {
      finish = resolve;
    }));

    const store = makeStore();
    const pending = fetchUserData(store.dispatch);
    expect(store.getState().user.session).toBe("pending");
    expect(store.getState().user.role).toBeNull();

    finish(meResponse(1));
    await expect(pending).resolves.toBe("manager");
    expect(store.getState().user.session).toBe("ready");
    expect(store.getState().user.role).toBe("manager");
  });

  it("turns a 401 into a logged-out session", async () => {
    localStorage.setItem("access_token", "expired");
    vi.mocked(customFetch).mockResolvedValue(new Response(null, { status: 401 }));

    const store = makeStore();
    await expect(refreshCurrentUser(store.dispatch, "establish")).rejects.toThrow(/401/);
    expect(localStorage.getItem("access_token")).toBeNull();
    expect(store.getState().user.session).toBe("unauthorized");
    expect(store.getState().user.role).toBeNull();
  });

  it("maps a regular user from /me", async () => {
    localStorage.setItem("access_token", "ok");
    vi.mocked(customFetch).mockResolvedValue(meResponse(0));
    const store = makeStore();
    await expect(fetchUserData(store.dispatch)).resolves.toBe("customer");
    expect(store.getState().user.role).toBe("customer");
  });
});
