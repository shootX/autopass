import { useSelector } from "react-redux";
import type { RootState } from "@/store";
import { smsStatusCopy, visibleSmsDelivery, type SmsDelivery, type SmsLang } from "@/lib/smsDelivery";

export default function SmsStatusNotice({
  delivery,
  detail,
}: {
  delivery: SmsDelivery | null;
  detail?: string | null;
}) {
  const lang = useSelector((state: RootState) => state.lang.currentLang);
  const shown = visibleSmsDelivery(delivery, import.meta.env.PROD);
  if (!shown) return null;
  const copy = smsStatusCopy(shown, lang satisfies SmsLang, detail);

  return (
    <p className="ap-sub" role="status" data-sms-delivery={shown}>
      <b>{copy.title}.</b> {copy.body}
    </p>
  );
}
