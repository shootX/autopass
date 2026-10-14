import { smsStatusCopy, type SmsDelivery } from "../../../src/lib/smsDelivery";

export default function SmsStatusNotice({
  delivery,
  detail,
}: {
  delivery: SmsDelivery | null;
  detail?: string | null;
}) {
  if (!delivery) return null;
  const copy = smsStatusCopy(delivery, "en", detail);
  return (
    <div className="cv-error-banner" role="status" data-sms-delivery={delivery}>
      <strong>{copy.title}.</strong> {copy.body}
    </div>
  );
}
