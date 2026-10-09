import { useState } from "react";
import { ArrowRight, CalendarCheck } from "lucide-react";
import { CAR_SEDAN, CAR_SUV } from "@/lib/v4";

const SLIDES = [
  {
    title: "რეცხვის პაკეტი",
    text: "აირჩიეთ რეცხვების რაოდენობა და გამოწერის ვადა. პაკეტის ფასი დამოკიდებულია ავტომობილის ტიპზე.",
  },
  {
    title: "რეცხვის დაჯავშნა",
    text: "აირჩიეთ ფილიალი, დღე და თავისუფალი დრო. ჯავშანი ჩანს კალენდარში.",
  },
  {
    title: "QR ფილიალში",
    text: "ფილიალში აჩვენეთ QR კოდი. ოპერატორი სკანირებს და რეცხვა ჩამოიჭრება პაკეტიდან.",
  },
];

export default function Onboarding({ onDone }: { onDone: () => void }) {
  const [index, setIndex] = useState(0);
  const slide = SLIDES[index];
  const last = index === SLIDES.length - 1;

  return (
    <div className="v4-onb">
      <div className="stage" />
      <div className="ring" style={{ width: 340, height: 340, background: "rgba(181,221,58,.13)" }} />
      <div className="ring" style={{ width: 230, height: 230, background: "rgba(181,221,58,.22)" }} />
      <div style={{ position: "absolute", right: 24, top: 28, fontWeight: 700, fontSize: 14, color: "var(--ap-gray-600)" }}>
        {index + 1} / {SLIDES.length}
      </div>
      {index === 0 && (
        <>
          <img src={CAR_SUV} alt="" style={{ position: "absolute", left: -10, top: 168, width: "108%", filter: "drop-shadow(0 18px 14px rgba(14,23,18,.18))" }} />
          <div className="v4-mini" style={{ left: 26, top: 92, width: 168 }}>
            <div className="v4-kicker">დარჩენილი რეცხვა</div>
            <div style={{ fontWeight: 800, fontSize: 22, marginTop: 2 }}>პაკეტი</div>
          </div>
        </>
      )}
      {index === 1 && (
        <>
          <img src={CAR_SEDAN} alt="" style={{ position: "absolute", left: 24, top: 150, width: "86%", filter: "drop-shadow(0 18px 14px rgba(14,23,18,.18))" }} />
          <div className="v4-mini" style={{ right: 24, top: 108, display: "flex", gap: 10, alignItems: "center" }}>
            <span className="v4-limeico" style={{ width: 34, height: 34, borderRadius: 10 }}>
              <CalendarCheck size={18} />
            </span>
            <div>
              <div className="v4-kicker">ჯავშანი</div>
              <div style={{ fontWeight: 800 }}>ფილიალი · დრო</div>
            </div>
          </div>
        </>
      )}
      {index === 2 && (
        <div className="v4-mini" style={{ left: "50%", top: 150, transform: "translateX(-50%)", width: 180, height: 180, display: "grid", placeItems: "center", borderRadius: 28 }}>
          <span className="v4-limeico" style={{ width: 72, height: 72, borderRadius: 20 }}>
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z" />
            </svg>
          </span>
        </div>
      )}
      <div className="panel v4-dk">
        <div className="dots">
          {SLIDES.map((_, i) => (
            <i key={i} className={i === index ? "on" : ""} />
          ))}
        </div>
        <h1>{slide.title}</h1>
        <p>{slide.text}</p>
        <button type="button" className="skip" onClick={onDone}>
          გამოტოვება
        </button>
        <button
          type="button"
          className="nx"
          onClick={() => {
            if (last) onDone();
            else setIndex((i) => i + 1);
          }}
        >
          {last ? "დაწყება" : "შემდეგი"} <ArrowRight size={20} strokeWidth={2.4} />
        </button>
      </div>
    </div>
  );
}
