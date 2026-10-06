import React, { useState } from "react";
import "../../styles/help.scss";
import { leftArrowUrl } from "@/assets/staticUrls";
import { useNavigate } from "react-router-dom";

const helpData = [
  {
    question: "What services do you offer?",
    answer:
      "We offer a full range of services to keep your car looking its best: from a quick express car wash to a comprehensive interior dry cleaning, rubber blackening, and protective wax coating. You can find the full list and current prices on the main screen in the \"Services\" section.",
  },
  {
    question: "Where are your car wash locations?",
    answer:
      "Our car washes are located throughout the city, so you can always find a convenient option along your route. You can view the interactive map, plan your route, and check the opening hours of each location in the \"Map\" tab (the geolocation icon in the bottom menu).",
  },
  {
    question: "Do I need to book an appointment?",
    answer:
      "To avoid wasting time in line, we recommend reserving a convenient slot in advance through the app (see the \"Booking\" tab). However, we are always happy to accommodate you on a first-come, first-served basis if there are available booths at the location!",
  },
  {
    question: "How long does a car wash take?",
    answer:
      "It all depends on the package you choose: Express wash: just 10-15 minutes. Standard package (body + interior): about 30-40 minutes. Premium services or dry cleaning: 1 hour or more. The administrator will tell you the exact time before the service begins.",
  },
];

export default function Help() {
  const [openIndex, setOpenIndex] = useState<number | null>(null);

  const toggle = (index: number) => {
    setOpenIndex(openIndex === index ? null : index);
  };

  const navigate = useNavigate();

  return (
    <div>
      <header>
        <img onClick={()=> navigate(-1)} src={leftArrowUrl} alt="" />
        Help
        <span></span>
      </header>

      <div className="help-wrapper">
        {helpData.map((item, index) => {
          const isOpen = openIndex === index;
          return (
            <div key={index} className="help-item" onClick={() => toggle(index)}>
              <div className="help-question">
                <p>{item.question}</p>
                <img
                  src={leftArrowUrl}
                  alt="toggle"
                  className={`arrow-icon ${isOpen ? "open" : ""}`}
                />
              </div>
              <div className={`help-answer ${isOpen ? "expanded" : ""}`}>
                <p>{item.answer}</p>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
