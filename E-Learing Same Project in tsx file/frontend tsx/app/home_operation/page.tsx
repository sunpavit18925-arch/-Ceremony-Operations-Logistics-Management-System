"use client";

import React from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";
import SystemBrand from "@/components/branding/SystemBrand";

export default function HomeOperationPage() {
  const { currentUser, currentRole } = useDemo();
  const is_super = currentRole === "super_admin";
  const user_fullname = currentUser ? `${currentUser.fname || ''} ${currentUser.lname || ''}`.trim() : "ฝ่ายปฏิบัติการ";
  const depart_name = currentUser?.department || "Ceremony Operations & Production Division";
  const role_banner_text = is_super ? "เห็นเมนูฝ่ายปฏิบัติการ" : "ฝ่ายปฏิบัติการ";

  return (
    <div>
      <div className="hero-section">
        <div className="container">
          <SystemBrand />
        </div>
      </div>

      <div className="container">
        <div className="row g-4 justify-content-center">
          <div className="col-12">
            <div
              className="alert alert-info shadow-sm"
              role="alert"
              style={{ padding: "1.5rem", fontSize: "1.2rem", borderLeft: "5px solid #0ea5e9" }}
            >
              <i className="bi bi-info-circle" style={{ fontSize: "1.5rem", marginRight: "0.5rem", color: "#0ea5e9" }}></i>
              <strong>
                ยินดีต้อนรับ {user_fullname} ({role_banner_text})
                {depart_name && (
                  <>
                    <br />
                    <small className="text-secondary" style={{ fontSize: "0.95rem", fontWeight: "normal" }}>
                      {depart_name}
                    </small>
                  </>
                )}
              </strong>
              <br />
              <span style={{ fontSize: "1rem", color: "var(--app-text)" }}>
                คลิกเลือกลิงก์ที่เมนูบาร์ หรือเลือกเมนูด้านล่างเพื่อเริ่มใช้งานระบบพลับพลาพิธี
              </span>
            </div>
          </div>

          <div className="col-12 col-sm-6 col-md-4 col-lg-3">
            <Link href="/staff_view_operation2" className="role-card text-decoration-none">
              <div className="role-icon" style={{ display: "flex", justifyContent: "center", alignItems: "center", gap: "15px", marginBottom: "10px" }}>
                <i className="bi bi-clipboard-check" style={{ color: "#6366f1" }}></i>
                <i className="bi bi-pencil-square" style={{ color: "#f97316" }}></i>
                <i className="bi bi-calendar-event" style={{ color: "#10b981" }}></i>
              </div>
              <h3 className="role-title">
                ตรวจสอบงานที่ได้รับมอบหมาย<br />และการประชุม
              </h3>
              <p style={{ fontSize: "0.85rem", color: "var(--app-muted)", marginTop: "0.5rem", marginBottom: 0 }}>
                ตรวจสอบงานที่ได้รับมอบหมาย, บันทึกข้อมูลการประชุม <br />และดูรายละเอียดการประชุม
              </p>
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}

