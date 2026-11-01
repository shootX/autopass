/* Site settings before the API answers. Admin > Website > Settings overrides prices, hours, contacts and links.
   Empty value = the part stays hidden or falls back, see README.md. */
window.AUTOPASS_APP_BASE = "https://autopass.ge";

/* App Store link. Empty: buttons stay on the page (#app). There is no web app since 2026-11-01. */
window.AUTOPASS_IOS_URL = "";
/* Image of a real QR code for AUTOPASS_IOS_URL (e.g. "img/app-store-qr.svg"). Empty: the QR card is hidden. */
window.AUTOPASS_IOS_QR = "";

/* Monthly price of a sedan package, by washes. */
window.AUTOPASS_PRICES = { 4: 60, 8: 110, 12: 150 };
window.AUTOPASS_HOURS = "08:00–22:00";

/* Footer contacts. Empty: the line is hidden. */
window.AUTOPASS_PHONE = "+995 32 2 00 00 00";
window.AUTOPASS_EMAIL = "hello@autopass.ge";
window.AUTOPASS_PRIVACY_URL = "";
window.AUTOPASS_TERMS_URL = "";

/* Content API (GET /site/{page}, POST /site/lead). Default: AUTOPASS_APP_BASE + "/api". */
window.AUTOPASS_API = "https://autopass.ge/api";
/* Empty SMS: the form is hidden. The business form posts to AUTOPASS_API /site/lead; set "" to send an e-mail instead. */
window.AUTOPASS_SMS_URL = "";
