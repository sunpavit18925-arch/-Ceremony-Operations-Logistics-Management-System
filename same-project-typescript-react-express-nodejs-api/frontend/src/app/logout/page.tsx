"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useDemo } from "@/context/DemoContext";

export default function LogoutPage() {
  const router = useRouter();
  const { logout, currentRole, isInitialized } = useDemo();

  useEffect(() => {
    if (!isInitialized) return;
    if (currentRole !== "guest") {
      logout();
      return;
    }
    router.replace("/?logged_out=1");
  }, [logout, currentRole, isInitialized, router]);

  return (
    <div className="d-flex align-items-center justify-content-center min-vh-100 bg-light">
      <div className="spinner-border text-primary" role="status">
        <span className="visually-hidden">กำลังออกจากระบบ...</span>
      </div>
    </div>
  );
}
