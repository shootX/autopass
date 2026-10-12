export const VOUCHER_CATEGORY_KEYS = ["All", "Auto", "Health", "Food", "Rest", "Booking"] as const;

export type VoucherCategoryKey = (typeof VOUCHER_CATEGORY_KEYS)[number];

export function voucherCategoryLabelKey(cat: string): string {
  return `Discounts.categories.${cat}`;
}
