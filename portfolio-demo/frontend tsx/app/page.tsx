"use client";

import React, { useEffect, useState, Suspense } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { useDemo } from "@/context/DemoContext";
import SystemBrand, { ComsLogo } from "@/components/branding/SystemBrand";

function LoginContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { currentUser, loginAsRole } = useDemo();
  const [loggedOut, setLoggedOut] = useState(false);

  useEffect(() => {
    if (searchParams.get("logged_out") === "1") {
      setLoggedOut(true);
    }
  }, [searchParams]);

  const handleRoleSelect = (roleId) => {
    loginAsRole(roleId);
    router.push("/welcome");
  };

  return (
    <div
      style={{
        background: "var(--app-login-bg)",
        minHeight: "100vh",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        padding: "20px 20px 90px",
        margin: 0
      }}
    >
      <div
        className="login-card"
        style={{
          border: "none",
          borderRadius: "20px",
          boxShadow: "0 15px 35px rgba(0,0,0,0.25)",
          padding: "35px 30px",
          textAlign: "center",
          maxWidth: "480px",
          width: "100%",
          background: "var(--app-surface)"
        }}
      >
        <div className="mb-3 text-center">
          <SystemBrand variant="login" />
          <p className="text-muted small mb-0">กรมโยธาธิการและผังเมือง</p>
        </div>

        {loggedOut && (
          <div className="alert alert-success py-2 px-3 small rounded-3 mb-3 text-start">
            <i className="bi bi-check-circle-fill me-1"></i> ออกจากระบบเรียบร้อยแล้ว
          </div>
        )}

        {/* Portfolio / Local Demo Sandbox Panel matching PHP index.php */}
        <div
          className="demo-panel"
          style={{
            backgroundColor: "var(--app-muted-bg)",
            border: "1px dashed var(--app-border)",
            borderRadius: "14px",
            padding: "18px",
            marginTop: "20px",
            textAlign: "left"
          }}
        >
          <div className="d-flex align-items-center justify-content-between mb-2">
            <span className="badge bg-warning text-dark px-2 py-1">
              <i className="bi bi-code-slash me-1"></i> Portfolio Demo Mode
            </span>
            <small className="text-muted" style={{ fontSize: "0.75rem" }}>
              ข้อมูลจำลอง 100%
            </small>
          </div>
          <p className="text-muted small mb-3" style={{ fontSize: "0.8rem" }}>
            สามารถเลือกทดสอบสิทธิ์ของแต่ละบทบาทได้ทันทีโดยไม่ต้องเชื่อมต่อ SSO จริง:
          </p>

          <div className="d-flex flex-wrap gap-1">
            <button
              type="button"
              onClick={() => handleRoleSelect("super_admin")}
              className="btn btn-outline-danger role-btn"
              style={{ fontSize: "0.8rem", padding: "6px 10px", borderRadius: "8px", marginBottom: "6px" }}
            >
              <i className="bi bi-shield-shaded me-1"></i> Super Admin
            </button>

            <button
              type="button"
              onClick={() => handleRoleSelect("admin")}
              className="btn btn-outline-dark role-btn"
              style={{ fontSize: "0.8rem", padding: "6px 10px", borderRadius: "8px", marginBottom: "6px" }}
            >
              <i className="bi bi-person-fill-gear me-1"></i> Admin
            </button>

            <button
              type="button"
              onClick={() => handleRoleSelect("head_operation")}
              className="btn btn-outline-warning role-btn text-dark"
              style={{ fontSize: "0.8rem", padding: "6px 10px", borderRadius: "8px", marginBottom: "6px" }}
            >
              <i className="bi bi-person-badge-fill me-1"></i> หน.ปฏิบัติการ
            </button>

            <button
              type="button"
              onClick={() => handleRoleSelect("coordinator_operation")}
              className="btn btn-outline-info role-btn text-dark"
              style={{ fontSize: "0.8rem", padding: "6px 10px", borderRadius: "8px", marginBottom: "6px" }}
            >
              <i className="bi bi-clipboard-check-fill me-1"></i> ผู้ประสานงาน
            </button>

            <button
              type="button"
              onClick={() => handleRoleSelect("staff_operation")}
              className="btn btn-outline-primary role-btn"
              style={{ fontSize: "0.8rem", padding: "6px 10px", borderRadius: "8px", marginBottom: "6px" }}
            >
              <i className="bi bi-person-workspace me-1"></i> จนท.ปฏิบัติการ
            </button>

            <button
              type="button"
              onClick={() => handleRoleSelect("head_vehicle")}
              className="btn btn-outline-success role-btn"
              style={{ fontSize: "0.8rem", padding: "6px 10px", borderRadius: "8px", marginBottom: "6px" }}
            >
              <i className="bi bi-truck-front-fill me-1"></i> หน.ยานยนต์
            </button>

            <button
              type="button"
              onClick={() => handleRoleSelect("staff_vehicle")}
              className="btn btn-outline-secondary role-btn"
              style={{ fontSize: "0.8rem", padding: "6px 10px", borderRadius: "8px", marginBottom: "6px" }}
            >
              <i className="bi bi-car-front-fill me-1"></i> จนท.ยานยนต์
            </button>

            <button
              type="button"
              onClick={() => handleRoleSelect("pending")}
              className="btn btn-outline-light text-muted border role-btn"
              style={{ fontSize: "0.8rem", padding: "6px 10px", borderRadius: "8px", marginBottom: "6px" }}
            >
              <i className="bi bi-hourglass-split me-1"></i> รออนุมัติ
            </button>
          </div>

          <hr className="my-2 border-secondary-subtle" />
          <small className="text-muted d-block" style={{ fontSize: "0.72rem" }}>
            * รหัสผ่านสำหรับบัญชีทดสอบทุกบทบาทคือ <code>password123</code>
          </small>
        </div>

        <div className="mt-4 text-center">
          <small className="text-muted d-inline-flex align-items-center justify-content-center gap-2" style={{ fontSize: "0.75rem" }}>
            <ComsLogo size={24} decorative />
            <span>&copy; 2026 COMS Project. All Rights Reserved.</span>
          </small>
        </div>
      </div>
    </div>
  );
}

export default function LoginPage() {
  return (
    <Suspense fallback={<div className="text-center p-5">Loading...</div>}>
      <LoginContent />
    </Suspense>
  );
}
