import { useNavigate } from "react-router-dom";
import { useTranslation } from "@/hooks/useTranslation";
import { leftArrowUrl } from "@/assets/staticUrls";
import Header from "@/components/Header";

export default function Messages() {
  const navigate = useNavigate();
  const t = useTranslation();
  return (
    <div>
      <Header
        variant="bar"
        title={t("Sidebar.menu.messages")}
        endSpacer
        left={<img onClick={() => navigate(-1)} src={leftArrowUrl} alt="" />}
      />
      {/* <div style={{ margin: "16px", padding: "16px", borderRadius: "16px", boxShadow: "2px 2px 2px 2px rgba(143, 143, 143, 0.1)" }}>
        <h4 style={{color: "#e4b23c", fontWeight: "600", marginBottom: "8px"}}>June 2023  17:00</h4>
        <p style={{color: "#12284b"}}>Thank you for visiting! We appreciate your feedback. Please leave a review of our service</p>
      </div> */}
      <p style={{ textAlign: "center", marginTop: "20px" }}>{t("Messages.empty")}</p>
    </div>
  );
}
