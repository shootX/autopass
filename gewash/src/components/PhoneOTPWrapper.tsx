import OTPVerification from "./OTPVerification";
import { customFetch } from "@/utils/customFetch";

export default function PhoneOTPWrapper({
  tempCode,
  phone,
  onSuccess,
}: {
  tempCode: number;
  phone: string;
  onSuccess: () => void;
}) {
  const handleVerify = async (code: string) => {
    const token = localStorage.getItem("access_token");
    const res = await customFetch(`${import.meta.env.VITE_API_URL}/change_phone/verify`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({
        temp_code: tempCode,
        code,
        phone,
      }),
    });

    const data = await res.json();
    if (data.success) {
      onSuccess();
    } else {
      throw new Error(data.message || "Verification failed");
    }
  };

  return <OTPVerification onVerify={handleVerify} />;
}
