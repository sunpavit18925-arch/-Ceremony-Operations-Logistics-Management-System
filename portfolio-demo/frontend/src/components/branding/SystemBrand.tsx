import Image from "next/image";

export const SYSTEM_NAME = "Ceremony Operations & Logistics Management System";

interface ComsLogoProps {
  size?: number;
  className?: string;
  decorative?: boolean;
}

export function ComsLogo({ size = 40, className = "", decorative = false }: ComsLogoProps) {
  return (
    <Image
      src="/icon.svg"
      alt={decorative ? "" : `COMS — ${SYSTEM_NAME}`}
      width={size}
      height={size}
      className={`coms-logo ${className}`.trim()}
      loading="eager"
      unoptimized
    />
  );
}

export default function SystemBrand({ variant = "hero" }: { variant?: "hero" | "login" | "welcome" }) {
  const isHero = variant === "hero";

  return (
    <div className={`coms-brand coms-brand--${variant}`}>
      <ComsLogo size={isHero ? 88 : 72} decorative />
      <div className="coms-brand-copy">
        {isHero ? (
          <>
            <span className="coms-brand-kicker" aria-hidden="true">COMS</span>
            <h1 className="hero-title">{SYSTEM_NAME}</h1>
          </>
        ) : (
          <>
            <h1 className="coms-brand-name">{variant === "login" ? "ระบบบริหารจัดการพิธีการและโลจิสติกส์" : "COMS Portal"}</h1>
            <p className="coms-brand-system-name text-muted">{SYSTEM_NAME}</p>
          </>
        )}
      </div>
    </div>
  );
}
