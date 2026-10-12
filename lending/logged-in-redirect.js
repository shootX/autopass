(function (root, factory) {
  var api = factory();
  if (typeof module === "object" && module.exports) module.exports = api;
  root.autopassLoggedInHome = api.loggedInHome;
})(typeof window !== "undefined" ? window : globalThis, function () {
  function loggedInHome(base, token, pathname) {
    if (!token) return null;
    var path = pathname || "/";
    if (path !== "/" && path !== "/index.html") return null;
    return String(base || "").replace(/\/+$/, "") + "/home";
  }
  return { loggedInHome: loggedInHome };
});
