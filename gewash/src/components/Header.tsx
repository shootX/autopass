import { useEffect, useState, type CSSProperties, type ReactNode } from "react";
import "../styles/header.scss";
import Sidebar from "../components/ui/Sidebar";
import { Link } from "react-router-dom";
import { Bell, Menu } from "lucide-react";
import { logoUrl } from "@/assets/staticUrls";

export type HeaderVariant = "shell" | "bar" | "v4" | "labeled";
export type HeaderTitleTag = "text" | "h1" | "h2" | "h3" | "p" | "span";

type Props = {
  variant?: HeaderVariant;
  logoVariant?: "image" | "calendar " | "qr";
  title?: ReactNode;
  titleAs?: HeaderTitleTag;
  titleClassName?: string;
  titleStyle?: CSSProperties;
  className?: string;
  style?: CSSProperties;
  left?: ReactNode;
  right?: ReactNode;
  rightSlot?: ReactNode;
  endSpacer?: boolean;
};

function Title({
  as,
  className,
  style,
  children,
}: {
  as: HeaderTitleTag;
  className?: string;
  style?: CSSProperties;
  children: ReactNode;
}) {
  if (as === "text") return <>{children}</>;
  const Tag = as;
  return (
    <Tag className={className} style={style}>
      {children}
    </Tag>
  );
}

export function HeaderBackChevron() {
  return (
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
      <path d="M15 18l-6-6 6-6" stroke="#14482F" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export default function Header({
  variant = "shell",
  title,
  titleAs = "text",
  titleClassName,
  titleStyle,
  className,
  style,
  left,
  right,
  rightSlot,
  endSpacer = false,
}: Props) {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  useEffect(() => {
    if (variant !== "shell") return;
    document.body.style.overflow = isSidebarOpen ? "hidden" : "auto";
    return () => {
      document.body.style.overflow = "auto";
    };
  }, [isSidebarOpen, variant]);

  if (variant === "v4") {
    return (
      <div className={className ?? "v4-hd"} style={style}>
        {left}
        <Title as={titleAs === "text" ? "h1" : titleAs} className={titleClassName} style={titleStyle}>
          {title}
        </Title>
        {right}
      </div>
    );
  }

  if (variant === "bar" || variant === "labeled") {
    const end = right ?? (endSpacer ? <span /> : null);
    return (
      <header className={className} style={style}>
        {left}
        <Title as={titleAs} className={titleClassName} style={titleStyle}>
          {title}
        </Title>
        {end}
      </header>
    );
  }

  return (
    <>
      <header className="header-container">
        <button
          type="button"
          onClick={() => setIsSidebarOpen(true)}
          aria-label="menu"
          style={{ border: 0, background: "transparent", color: "var(--ap-forest)", display: "grid" }}
        >
          <Menu size={24} />
        </button>
        {title ? (
          <h2 className="header-title">{title}</h2>
        ) : (
          <img src={logoUrl} alt="autopass" style={{ height: 26, width: "auto" }} />
        )}
        {rightSlot ?? (
          <Link to="/messages" aria-label="messages" style={{ color: "var(--ap-forest)", display: "grid" }}>
            <Bell size={22} />
          </Link>
        )}
      </header>
      {isSidebarOpen && <Sidebar onClose={() => setIsSidebarOpen(false)} />}
    </>
  );
}
