import { CAR_SUV } from "@/lib/v4";
import { logoUrl } from "@/assets/staticUrls";

export default function SplashScreen() {
  return (
    <div className="v4-splash" role="status" aria-label="autopass">
      <div className="glow" />
      <img className="logo" src={logoUrl} alt="autopass" />
      <div className="tag">რეცხვის პაკეტი · ჯავშანი · QR</div>
      <img className="car" src={CAR_SUV} alt="" />
    </div>
  );
}
