export const NATIVE_LANGUAGE_LABELS: Record<string, string> = {
  en: "English",
  ka: "ქართული",
  ru: "Русский",
};

export function languageNativeName(code: string): string {
  return NATIVE_LANGUAGE_LABELS[code] ?? NATIVE_LANGUAGE_LABELS.ka;
}
