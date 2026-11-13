"use client";

import React from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useDemo } from "@/context/DemoContext";
import SystemBrand from "@/components/branding/SystemBrand";

export default function WelcomePage() {
  const router = useRouter();
  const { currentUser, currentRole, isInitialized, logout } = useDemo();

  const roleNameTh = {
    super_admin: "ผู้ดูแลระบบสูงสุด (Super Admin)",
    admin: "ผู้ดูแลระบบ (Admin)",
    head_operation: "หัวหน้าฝ่ายปฏิบัติการ",
    coordinator_operation: "ฝ่ายปฏิบัติการ (ผู้ประสานงาน)",
    staff_operation: "เจ้าหน้าที่ฝ่ายปฏิบัติการ",
    head_vehicle: "หัวหน้าฝ่ายยานยนต์",
    staff_vehicle: "เจ้าหน้าที่ฝ่ายยานยนต์",
    pending: "รออนุมัติสิทธิ์การใช้งาน"
  };

  const effectiveRole = isInitialized && currentUser ? currentRole : "admin";

  let targetPage = "/home_admin";
  switch (effectiveRole) {
    case "super_admin":
    case "admin":
      targetPage = "/home_admin";
      break;
    case "head_operation":
      targetPage = "/home_head_operation";
      break;
    case "coordinator_operation":
      targetPage = "/home_operation";
      break;
    case "staff_operation":
      targetPage = "/staff_view_operation";
      break;
    case "head_vehicle":
      targetPage = "/home_head_vehicle";
      break;
    case "staff_vehicle":
      targetPage = "/home_vehicle";
      break;
    case "pending":
    default:
      targetPage = "#";
      break;
  }

  const handleLogout = () => {
    logout();
    router.push("/?logged_out=1");
  };

  const user = currentUser || {
    fname: "ประสิทธิ์",
    lname: "จัดการงาน",
    department: "กองกลาง ฝ่ายสารบรรณ",
    role: effectiveRole
  };

  return (
    <div
      style={{
        background: "var(--app-welcome-bg)",
        minHeight: "100vh",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        margin: 0,
        padding: "20px 20px 90px"
      }}
    >
      <div
        className="welcome-card"
        style={{
          background: "var(--app-surface)",
          borderRadius: "1.5rem",
          border: "none",
          boxShadow: "0 15px 35px rgba(0, 0, 0, 0.2)",
          overflow: "hidden",
          width: "100%",
          maxWidth: "480px"
        }}
      >
        <div
          className="card-header-custom text-center"
          style={{
            background: "var(--app-surface)",
            borderBottom: "1px solid var(--app-border)",
            padding: "1.75rem 1rem 1rem"
          }}
        >
          <SystemBrand variant="welcome" />
        </div>

        <div className="card-body p-4 text-center">
          <div
            className="icon-circle"
            style={{
              width: "90px",
              height: "90px",
              background: "var(--bs-success-bg-subtle)",
              color: "var(--bs-success-text-emphasis)",
              borderRadius: "50%",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              margin: "0 auto 1.25rem",
              fontSize: "2.75rem"
            }}
          >
            <i className="bi bi-person-check-fill"></i>
          </div>

          <h6 className="text-muted mb-1">ยินดีต้อนรับ</h6>
          <h4 className="fw-bold mb-3 text-dark">
            {user.fname} {user.lname}
          </h4>

          <div className="mb-4">
            <p className="mb-1 text-secondary small">สถานะการเข้าใช้งาน</p>
            <div
              className="role-badge"
              style={{
                display: "inline-block",
                padding: "0.5rem 1.25rem",
                background: "linear-gradient(45deg, #2e7d32, #43a047)",
                color: "white",
                borderRadius: "50px",
                fontWeight: 500,
                fontSize: "0.9rem"
              }}
            >
              <i className="bi bi-shield-lock me-1"></i>
              {roleNameTh[currentRole] || currentRole}
            </div>
            {user.department && (
              <div className="mt-2 text-muted small">
                <i className="bi bi-building me-1"></i> {user.department}
              </div>
            )}
          </div>

          {currentRole === "pending" ? (
            <div className="alert alert-warning py-2 small mb-4">
              <i className="bi bi-hourglass-split me-1"></i> บัญชีของคุณอยู่ระหว่างรอผู้ดูแลระบบกำหนดสิทธิ์การใช้งาน
            </div>
          ) : (
            <div className="d-grid gap-2">
              <Link
                href={targetPage}
                className="btn btn-main btn-lg text-decoration-none"
                style={{
                  background: "linear-gradient(45deg, #11998e, #38ef7d)",
                  border: "none",
                  color: "white",
                  padding: "12px 30px",
                  borderRadius: "50px",
                  fontWeight: 600,
                  letterSpacing: "0.5px"
                }}
              >
                เข้าสู่หน้าหลัก <i className="bi bi-chevron-right ms-1"></i>
              </Link>
            </div>
          )}

          <div className="mt-4 pt-2 border-top">
            <button
              onClick={handleLogout}
              className="text-danger small text-decoration-none border-0 bg-transparent"
            >
              <i className="bi bi-box-arrow-right me-1"></i> ออกจากระบบ
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

