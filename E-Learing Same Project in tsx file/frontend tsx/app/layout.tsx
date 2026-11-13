import "./globals.css";
import React, { ReactNode } from "react";
import { DemoProvider } from "@/context/DemoContext";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import ThemeToggle from "@/components/layout/ThemeToggle";
import { themeInitScript } from "@/lib/theme";

export const metadata = {
  title: "ระบบพลับพลาพิธี - กรมโยธาธิการและผังเมือง",
  description: "ระบบบริหารจัดการงานพลับพลาพิธี กรมโยธาธิการและผังเมือง ศูนย์เทคโนโลยีสารสนเทศ (Full Stack TypeScript Portfolio Demo)",
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="th" data-scroll-behavior="smooth" data-bs-theme="light" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeInitScript }} />
        <link
          href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap"
          rel="stylesheet"
        />
      </head>
      <body>
        <DemoProvider>
          <Navbar />
          {children}
          <Footer />
        </DemoProvider>
        <ThemeToggle />
      </body>
    </html>
  );
}

