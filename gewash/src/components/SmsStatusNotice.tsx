import { useSelector } from "react-redux";
import type { RootState } from "@/store";
import { smsStatusCopy, type SmsDelivery, type SmsLang } from "@/lib/smsDelivery";

export default function SmsStatusNotice({
  delivery,
  detail,
}: {
  delivery: SmsDelivery | null;
  detail?: string | null;
}) {
  const lang = useSelector((state: RootState) => state.lang.currentLang);
  if (!delivery) return null;
  const copy = smsStatusCopy(delivery, lang satisfies SmsLang, detail);

  return (
    <p className="ap-sub" role="status" data-sms-delivery={delivery}>
      <b>{copy.title}.</b> {copy.body}
    </p>
  );
}
