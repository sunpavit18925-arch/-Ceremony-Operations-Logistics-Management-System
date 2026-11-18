"use client";

import React from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";
import SystemBrand from "@/components/branding/SystemBrand";

export default function HomeHeadVehiclePage() {
  const { currentUser, currentRole } = useDemo();
  const is_super = currentRole === "super_admin";
  const user_fullname = currentUser ? `${currentUser.fname || ''} ${currentUser.lname || ''}`.trim() : "หัวหน้าฝ่ายยานยนต์";
  const depart_name = currentUser?.department || "ฝ่ายยานพาหนะและขนส่ง";
  const role_banner_text = is_super ? "เห็นเมนูหัวหน้าฝ่ายยานยนต์" : "หัวหน้าฝ่ายยานยนต์";

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
              className="alert alert-info shadow-sm rounded-3"
              role="alert"
              style={{ padding: "1.25rem", borderLeft: "5px solid #0ea5e9" }}
            >
              <i className="bi bi-info-circle fs-4 me-2 text-info"></i>
              <strong>
                ยินดีต้อนรับ {user_fullname} ({role_banner_text})
                {depart_name && (
                  <>
                    <br />
                    <small className="text-secondary">{depart_name}</small>
                  </>
                )}
              </strong>
              <br />
              <span className="small text-secondary">
                คลิกเลือกลิงก์ที่เมนูบาร์ หรือเลือกเมนูด้านล่างเพื่อเริ่มใช้งานระบบ
              </span>
            </div>
          </div>

          <div className="col-12 col-sm-6 col-md-4 col-lg-3">
            <Link href="/select_mission" className="role-card text-decoration-none">
              <div className="role-icon">
                <i className="bi bi-car-front" style={{ color: "#10b981" }}></i>
              </div>
              <h3 className="role-title">การบริหารจัดการรถ</h3>
              <p style={{ fontSize: "0.85rem", color: "var(--app-muted)", marginTop: "0.5rem", marginBottom: 0 }}>
                จัดรถ (ทีม) จัดคนขับรถ และระบุประเภทรถให้เหมาะสม
              </p>
            </Link>
          </div>

          <div className="col-12 col-sm-6 col-md-4 col-lg-3">
            <Link href="/manage_vehicles" className="role-card text-decoration-none">
              <div className="role-icon">
                <i className="bi bi-truck" style={{ color: "#10b981" }}></i>
              </div>
              <h3 className="role-title">จัดการรถ</h3>
              <p style={{ fontSize: "0.85rem", color: "var(--app-muted)", marginTop: "0.5rem", marginBottom: 0 }}>
                จัดการข้อมูลยานพาหนะ
              </p>
            </Link>
          </div>

          <div className="col-12 col-sm-6 col-md-4 col-lg-3">
            <Link href="/manage_drivers" className="role-card text-decoration-none">
              <div className="role-icon">
                <i className="bi bi-person-gear" style={{ color: "#10b981" }}></i>
              </div>
              <h3 className="role-title">จัดการคน</h3>
              <p style={{ fontSize: "0.85rem", color: "var(--app-muted)", marginTop: "0.5rem", marginBottom: 0 }}>
                จัดการข้อมูลพนักงานขับรถ
              </p>
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}

