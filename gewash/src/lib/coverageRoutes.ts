/** Aliases from docs/gewash-frontend-coverage.md that already have a screen. */
export const customerAliasPaths = {
  home: "/home",
  qr: "/qr",
  reviews: "/reviews",
  referrals: "/referrals",
  calendar: "/calendar",
  customerCalendar: "/customer-calendar",
  profileCars: "/profile/cars",
  profileCarsNew: "/profile/cars/new",
  profileCarEdit: "/profile/cars/:id/edit",
} as const;

export const managerAliasPaths = {
  home: "/home",
  calendar: "/manager/calendar",
  bookings: "/manager/bookings",
  scanner: "/manager/scanner",
  statistics: "/manager/statistics",
  profile: "/manager/profile",
  contacts: "/manager/contacts",
  help: "/manager/help",
  settings: "/manager/settings",
  reschedule: "/manager/reschedule/:id",
} as const;

/**
 * Coverage routes with no screen in this repo (no gewash/src/quiet/).
 * Not invented here.
 */
export const missingCoverageRoutes = [
  "/reset-password",
  "/verify-email",
  "/terms",
  "/bookings",
  "/bookings/:id",
  "/packages",
  "/packages/:id",
  "/packages/:id/checkout",
  "/my-points/history",
  "/my-area/vouchers/:id",
  "/my-area/tickets/:id",
  "/shop/:id/checkout",
  "/orders/:id",
  "/reviews/new",
  "/messages/:id",
  "/notifications",
  "/help/:slug",
  "/support",
  "/settings/language",
  "/session-expired",
  "/403",
  "/500",
  "/maintenance",
  "/offline",
] as const;
