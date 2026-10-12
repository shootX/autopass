import { createRequire } from "node:module";
import { describe, expect, it } from "vitest";

const require = createRequire(import.meta.url);
const { loggedInHome } = require("../../../lending/logged-in-redirect.js") as {
  loggedInHome: (base: string, token: string | null, pathname: string) => string | null;
};

describe("logged-in marketing redirect", () => {
  it("sends a token holder from / to the SPA home", () => {
    expect(loggedInHome("https://wash.socialsave.cc", "abc", "/")).toBe(
      "https://wash.socialsave.cc/home",
    );
  });

  it("leaves guests on the marketing page", () => {
    expect(loggedInHome("https://wash.socialsave.cc", null, "/")).toBeNull();
  });

  it("does not redirect other marketing paths", () => {
    expect(loggedInHome("https://wash.socialsave.cc/", "abc", "/home")).toBeNull();
  });
});
