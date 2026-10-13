import { configureStore } from "@reduxjs/toolkit";
import { cleanup, render, screen } from "@testing-library/react";
import { Provider } from "react-redux";
import { afterEach, describe, expect, it } from "vitest";
import langReducer from "@/store/langSlice";
import SmsStatusNotice from "./SmsStatusNotice";

afterEach(() => {
  cleanup();
});

describe("SmsStatusNotice", () => {
  it("shows the accepted status and not a delivery confirmation", () => {
    const store = configureStore({
      reducer: { lang: langReducer },
      preloadedState: { lang: { currentLang: "en", translations: {} } },
    });
    render(
      <Provider store={store}>
        <SmsStatusNotice delivery="accepted" />
      </Provider>,
    );
    const status = screen.getByRole("status");
    expect(status.getAttribute("data-sms-delivery")).toBe("accepted");
    expect(status.textContent).toContain("Accepted");
    expect(status.textContent).toContain("does not mean delivered");
    expect(status.textContent?.toLowerCase()).not.toMatch(/has been delivered|was delivered/);
  });

  it("labels a simulated send as test mode", () => {
    const store = configureStore({
      reducer: { lang: langReducer },
      preloadedState: { lang: { currentLang: "ka", translations: {} } },
    });
    render(
      <Provider store={store}>
        <SmsStatusNotice delivery="simulated" />
      </Provider>,
    );
    expect(screen.getByRole("status").textContent).toContain("სატესტო რეჟიმი");
  });
});
