import { useNavigate } from "react-router-dom";
import { useTranslation } from "@/hooks/useTranslation";
import { leftArrowUrl } from "@/assets/staticUrls";

export default function Messages() {
  const navigate = useNavigate();
  const t = useTranslation();
  return (
    <div>
      <header>
        <img onClick={()=> navigate(-1)} src={leftArrowUrl} alt='' />
        {t("Sidebar.menu.messages")}
        <span></span>
      </header>
      {/* <div style={{ margin: "16px", padding: "16px", borderRadius: "16px", boxShadow: "2px 2px 2px 2px rgba(143, 143, 143, 0.1)" }}>
        <h4 style={{color: "#B5DD3A", fontWeight: "600", marginBottom: "8px"}}>June 2023  17:00</h4>
        <p style={{color: "#14482F"}}>Thank you for visiting! We appreciate your feedback. Please leave a review of our service</p>
      </div> */}
      <p style={{ textAlign: "center", marginTop: "20px" }}>{t("Messages.empty")}</p>
    </div>
  );
}
