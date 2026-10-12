export const AUTO_RENEWAL_DEFAULT = false;

export function shouldShowPackageForm(editing: boolean, buying: boolean): boolean {
  return editing || buying;
}
