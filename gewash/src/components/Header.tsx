import React, { useEffect, useState } from 'react';
import "../styles/header.scss";
import Sidebar from '../components/ui/Sidebar';
import { Link } from 'react-router-dom';

type Props = {
  logoVariant?: "image" | "calendar " | "qr";
  title?: string;
  rightSlot?: React.ReactNode;
};


export default function Header({ logoVariant = "image", title, rightSlot }: Props) {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  useEffect(() => {
    document.body.style.overflow = isSidebarOpen ? "hidden" : "auto";
    return () => {
      document.body.style.overflow = "auto";
    };
  }, [isSidebarOpen]);

  return (
    <>
      <header className='header-container'>
  <img
    className='cursor-pointer'
    src="../../icons/b-menu.svg"
    alt="burger-menu"
    onClick={() => setIsSidebarOpen(true)}
  />

  {title ? (
    <h2 className='header-title'>{title}</h2>
  ) : (
    <span className="gewash-word">GEWASH</span>
  )}

  {rightSlot ?? (
    <Link to="/messages">
      <img src="../../icons/bell_active_icon.svg" alt="notification-bell" />
    </Link>
  )}
</header>

      {isSidebarOpen && (
        <Sidebar onClose={() => setIsSidebarOpen(false)} />
      )}
    </>
  );
}
