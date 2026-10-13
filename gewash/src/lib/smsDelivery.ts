export type SmsDelivery = "simulated" | "accepted" | "failed" | "unknown";
export type SmsLang = "ka" | "en" | "ru";

const DELIVERIES: readonly SmsDelivery[] = ["simulated", "accepted", "failed", "unknown"];

export function parseSmsDelivery(value: unknown): SmsDelivery | null {
  return DELIVERIES.find((status) => status === value) ?? null;
}

/** Drop anything that could be a one-time code. The OTP is never shown. */
export function publicSmsDetail(value: unknown): string | null {
  if (typeof value !== "string") return null;
  const text = value.trim();
  if (!text || /\d{6}/.test(text)) return null;
  return text;
}

type SmsCopy = { title: string; body: string };

const COPY: Record<SmsDelivery, Record<SmsLang, SmsCopy>> = {
  simulated: {
    ka: {
      title: "სატესტო რეჟიმი",
      body: "კოდი შეიქმნა სატესტო რეჟიმში. შეტყობინება ტელეფონზე არ იგზავნება.",
    },
    en: {
      title: "Test mode",
      body: "The code was created in test mode. The message is not sent to the phone.",
    },
    ru: {
      title: "Тестовый режим",
      body: "Код создан в тестовом режиме. Сообщение на телефон не отправляется.",
    },
  },
  accepted: {
    ka: {
      title: "მიღებულია",
      body: "მოთხოვნა მიღებულია. შეიყვანეთ კოდი, როცა შეტყობინება მოგივათ. მიღება არ ნიშნავს მიტანას.",
    },
    en: {
      title: "Accepted",
      body: "The request was accepted. Enter the code when the message arrives. Accepted does not mean delivered.",
    },
    ru: {
      title: "Принято",
      body: "Запрос принят. Введите код, когда сообщение придёт. Принято не значит доставлено.",
    },
  },
  failed: {
    ka: { title: "ვერ გაიგზავნა", body: "შეტყობინება ვერ გაიგზავნა. სცადეთ ცოტა ხანში." },
    en: { title: "Not sent", body: "The message was not sent. Try again in a moment." },
    ru: { title: "Не отправлено", body: "Сообщение не отправлено. Попробуйте чуть позже." },
  },
  unknown: {
    ka: {
      title: "შედეგი უცნობია",
      body: "გაგზავნის შედეგი ვერ დადგინდა. თუ კოდი მოგივიდათ, გამოიყენეთ იგი. ხელახლა ავტომატურად არ იგზავნება.",
    },
    en: {
      title: "Result unknown",
      body: "The send result could not be confirmed. If the code arrives, use it. It is not sent again automatically.",
    },
    ru: {
      title: "Результат неизвестен",
      body: "Результат отправки не подтверждён. Если код придёт, используйте его. Повторно он сам не отправится.",
    },
  },
};

export function smsStatusCopy(
  status: SmsDelivery,
  lang: SmsLang,
  detail?: string | null,
): SmsCopy & { simulated: boolean } {
  const copy = COPY[status][lang];
  const safe = publicSmsDetail(detail);
  const body = status === "failed" && safe ? safe : copy.body;
  return { title: copy.title, body, simulated: status === "simulated" };
}
