import React, { useEffect, useState } from 'react';
import "../styles/header.scss";
import Sidebar from '../components/ui/Sidebar';
import { Link } from 'react-router-dom';
import { Bell, Menu } from 'lucide-react';
import { logoUrl } from '@/assets/staticUrls';

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
  <button type="button" onClick={() => setIsSidebarOpen(true)} aria-label="menu" style={{ border: 0, background: 'transparent', color: 'var(--ap-forest)', display: 'grid' }}>
    <Menu size={24} />
  </button>

  {title ? (
    <h2 className='header-title'>{title}</h2>
  ) : (
    <img src={logoUrl} alt="autopass" style={{ height: 26, width: 'auto' }} />
  )}

  {rightSlot ?? (
    <Link to="/messages" aria-label="messages" style={{ color: 'var(--ap-forest)', display: 'grid' }}>
      <Bell size={22} />
    </Link>
  )}
</header>

      {isSidebarOpen && (
        <Sidebar onClose={() => setIsSidebarOpen(false)} />
      )}
    </>
  );
}
