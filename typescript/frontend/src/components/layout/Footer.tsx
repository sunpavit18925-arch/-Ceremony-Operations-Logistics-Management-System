"use client";

import React from "react";
import { usePathname } from "next/navigation";
import { useDemo } from "@/context/DemoContext";
import { ComsLogo } from "@/components/branding/SystemBrand";

export default function Footer() {
  const pathname = usePathname();
  const { currentUser } = useDemo();

  // On login page (root) and welcome page, do not show footer
  if (!currentUser || pathname === "/" || pathname === "/welcome") {
    return null;
  }

  return (
    <footer className="main-footer">
      <div className="container text-center">
        <div className="footer-text d-flex align-items-center justify-content-center gap-2">
          <ComsLogo size={28} decorative />
          <span>&copy; 2026 COMS Project. All Rights Reserved.</span>
        </div>
      </div>
    </footer>
  );
}

