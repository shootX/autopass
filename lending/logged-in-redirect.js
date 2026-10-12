(function (root, factory) {
  var api = factory();
  var redirect = api.redirectIfLoggedIn;
  redirect.loggedInHome = api.loggedInHome;
  if (typeof module === "object" && module.exports) {
    module.exports = redirect;
  }
  function entry(a, b, c) {
    if (a && typeof a === "object") return redirect(a);
    return api.loggedInHome(a, b, c);
  }
  entry.loggedInHome = api.loggedInHome;
  root.autopassLoggedInHome = entry;
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  function loggedInHome(base, token, pathname) {
    if (!token) return null;
    var path = pathname || "/";
    if (path !== "/" && path !== "/index.html") return null;
    return String(base || "").replace(/\/+$/, "") + "/home";
  }

  function redirectIfLoggedIn(options) {
    var opts = options || {};
    if (!opts.token) return false;
    var base = String(opts.appBase || "").replace(/\/+$/, "");
    var home = base + "/home";
    if (typeof opts.replace === "function") opts.replace(home);
    return true;
  }

  return { loggedInHome: loggedInHome, redirectIfLoggedIn: redirectIfLoggedIn };
});
