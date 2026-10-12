import { configureStore } from "@reduxjs/toolkit";
import { cleanup, render, screen } from "@testing-library/react";
import { Provider } from "react-redux";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import userReducer from "@/store/userSlice";
import type { SessionPhase, UserRole } from "@/store/userSlice";
import { CarCheckRoute } from "./CarCheckRoute";

const fetchCars = vi.hoisted(() => vi.fn());

vi.mock("@/hooks/useFetchCars", () => ({
  useFetchCars: () => fetchCars(),
}));

function renderAt(path: string, role: UserRole | null, session: SessionPhase) {
  const store = configureStore({
    reducer: { user: userReducer },
    preloadedState: {
      user: { role, data: null, session },
    },
  });

  return render(
    <Provider store={store}>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route element={<CarCheckRoute />}>
            <Route path="/home" element={<div>home</div>} />
            <Route path="/manager-qr-page" element={<div>manager-page</div>} />
          </Route>
          <Route path="/add-car" element={<div>add-car</div>} />
          <Route path="/auth" element={<div>auth</div>} />
        </Routes>
      </MemoryRouter>
    </Provider>,
  );
}

beforeEach(() => {
  localStorage.clear();
  fetchCars.mockReset();
  fetchCars.mockReturnValue({ cars: [], loading: false, error: "" });
});

afterEach(() => {
  cleanup();
});

describe("CarCheckRoute", () => {
  it("lets a manager through to the manager page without loading cars", () => {
    localStorage.setItem("access_token", "manager-token");
    renderAt("/manager-qr-page", "manager", "ready");

    expect(screen.getByText("manager-page")).toBeTruthy();
    expect(screen.queryByText("add-car")).toBeNull();
    expect(fetchCars).not.toHaveBeenCalled();
  });

  it("sends a regular user without a car to car registration", () => {
    localStorage.setItem("access_token", "user-token");
    fetchCars.mockReturnValue({ cars: [], loading: false, error: "" });
    renderAt("/home", "customer", "ready");

    expect(screen.getByText("add-car")).toBeTruthy();
    expect(fetchCars).toHaveBeenCalled();
  });

  it("does not redirect before the role is known", () => {
    localStorage.setItem("access_token", "pending-token");
    renderAt("/home", null, "pending");

    expect(screen.getByText("home")).toBeTruthy();
    expect(screen.queryByText("add-car")).toBeNull();
    expect(screen.queryByText("auth")).toBeNull();
    expect(fetchCars).not.toHaveBeenCalled();
  });
});
