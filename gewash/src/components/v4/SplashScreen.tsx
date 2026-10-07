import { CAR_SUV } from "@/lib/v4";
import { Mark } from "./Mark";

export default function SplashScreen() {
  return (
    <div className="v4-splash" role="status" aria-label="autopass">
      <div className="glow" />
      <div className="mark">
        <Mark light size={52} />
      </div>
      <div className="wm">autopass</div>
      <div className="tag">რეცხვის პაკეტი · ჯავშანი · QR</div>
      <img className="car" src={CAR_SUV} alt="" />
    </div>
  );
}
