(function (root, factory) {
  var redirect = factory();
  if (typeof module === "object" && module.exports) {
    module.exports = redirect;
  }
  root.autopassLoggedInHome = redirect;
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  function redirectIfLoggedIn(options) {
    var opts = options || {};
    if (!opts.token) return false;
    var base = String(opts.appBase || "").replace(/\/+$/, "");
    var home = base + "/home";
    if (typeof opts.replace === "function") opts.replace(home);
    return true;
  }
  return redirectIfLoggedIn;
});
