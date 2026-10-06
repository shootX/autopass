import React from "react";
import "../styles/Contacts.scss";
import { useNavigate } from "react-router-dom";
import { useFetchContacts } from "@/hooks/useFetchContacts";
import { leftArrowUrl } from "@/assets/staticUrls";

export default function Contacts() {
  const navigate = useNavigate();
  const { contacts, loading, error } = useFetchContacts();

  return (
    <div>
      <header>
        <img onClick={() => navigate(-1)} src={leftArrowUrl} alt="" />
        Contacts
        <span></span>
      </header>

      <div className="contacts-wrapper">
        <h4>Our main office</h4>

        {loading && <p>Loading...</p>}
        {error && <p className="error">{error}</p>}

        {contacts && (
          <>
            <label>Address</label>
            <h4>{contacts.address}</h4>

            <label>Phone</label>
            <h4>{contacts.phone}</h4>

            <label>Email</label>
            <h4>{contacts.email}</h4>

            <label>Website</label>
            <h4>{contacts.website}</h4>

            <label>Working Hours</label>
            <h4>{contacts.working_hours}</h4>
          </>
        )}
      </div>
    </div>
  );
}
