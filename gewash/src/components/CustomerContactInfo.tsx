import { Link } from "react-router-dom";
import { useUser } from "@/hooks/useUser";
import CustomerInfoSkeleton from "./Skeletons/CustomerInfoSkeleton";
import { useSelector } from "react-redux";
import type { RootState } from "@/store";
import { useTranslation } from "@/hooks/useTranslation";
import { editNoteIconUrl } from "@/assets/staticUrls";
import { formatPhone } from "@/lib/format";

export default function CustomerContactInfo() {
  const user = useSelector((state: RootState) => state.user.data);
  const t = useTranslation();

  const emailStatus = user?.emailVerified
    ? t("CustomerContactInfo.email.confirmed")
    : t("CustomerContactInfo.email.unconfirmed");

  const emailColor = user?.emailVerified ? "#4CAF50" : "#D64541";

  return (
    <div className='customer-contact-info-wrapper'>
      <div className='customer-contact-info-container'>
        <h1>{t("CustomerContactInfo.title")}</h1>

        {!user ? (
          <CustomerInfoSkeleton />
        ) : (
          <>
            <div className='customer-phone-number'>
              <div>
                <p>{t("CustomerContactInfo.phone.label")}</p>
                <p>
                  {/* <span className='dot-status'></span> */}
                  {formatPhone(user.phone)}
                </p>
              </div>
              <div>
                <Link to='/change-phone'>
                  <img
                    src={editNoteIconUrl}
                    alt={t("CustomerContactInfo.phone.editAlt")}
                  />
                </Link>
              </div>
            </div>

            <div className='customer-email'>
              <div>
                <p>
                  {t("CustomerContactInfo.email.label")}{" "}
                  <span
                    className='verification-status'
                    style={{ color: emailColor }}
                  >
                    &nbsp;{emailStatus}
                  </span>
                </p>
                <p>
                  <span className='dot-status'></span> {user.email}
                </p>
              </div>
              <div>
                <Link to='/change-email'>
                  <img
                    src={editNoteIconUrl}
                    alt={t("CustomerContactInfo.email.editAlt")}
                  />
                </Link>
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
