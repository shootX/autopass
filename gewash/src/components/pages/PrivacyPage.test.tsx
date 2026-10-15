import { cleanup, render, screen } from "@testing-library/react";
import { configureStore } from "@reduxjs/toolkit";
import { Provider } from "react-redux";
import { MemoryRouter } from "react-router-dom";
import { afterEach, describe, expect, it } from "vitest";
import ka from "../../../ka.json";
import en from "../../../en.json";
import ru from "../../../ru.json";
import langReducer from "@/store/langSlice";
import PrivacyPage from "./PrivacyPage";

type Dictionary = { Privacy?: Record<string, string> };

function renderPolicy(dictionary: Dictionary) {
  const store = configureStore({
    reducer: { lang: langReducer },
    preloadedState: {
      lang: { currentLang: "ka", translations: dictionary },
    },
  });
  return render(
    <Provider store={store}>
      <MemoryRouter>
        <PrivacyPage />
      </MemoryRouter>
    </Provider>,
  );
}

afterEach(() => {
  cleanup();
});

describe("privacy policy copy", () => {
  it("has a Georgian version and keeps the English text", () => {
    const georgian = (ka as Dictionary).Privacy?.title ?? "";
    const english = (en as Dictionary).Privacy?.title ?? "";
    const russian = (ru as Dictionary).Privacy?.title ?? "";
    expect(georgian).toMatch(/კონფიდენციალურ/);
    expect(english).toBe("Privacy Policy");
    expect(russian.length).toBeGreaterThan(0);
    expect(georgian).not.toBe(english);
    expect((ka as Dictionary).Privacy?.intro ?? "").not.toMatch(/\[cite:/);
    expect((en as Dictionary).Privacy?.intro ?? "").toMatch(/autopass/);
    expect((en as Dictionary).Privacy?.intro ?? "").not.toMatch(/\[cite:/);
  });

  it("renders the Georgian policy from i18n", () => {
    renderPolicy(ka as Dictionary);
    expect(screen.getByRole("heading", { name: "კონფიდენციალურობის პოლიტიკა" })).toBeTruthy();
    expect(screen.getByRole("button", { name: "წავიკითხე, უკან" })).toBeTruthy();
  });

  it("renders the English policy when those strings are loaded", () => {
    renderPolicy(en as Dictionary);
    expect(screen.getByRole("heading", { name: "Privacy Policy" })).toBeTruthy();
    expect(screen.getByRole("button", { name: "Read it, come back" })).toBeTruthy();
  });
});
