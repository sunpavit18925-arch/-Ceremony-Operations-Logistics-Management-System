"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";
import SystemBrand from "@/components/branding/SystemBrand";
import { api } from "@/services/api";

export default function HomeHeadOperationPage() {
  const { currentUser, currentRole } = useDemo();
  const [unassignedCount, setUnassignedCount] = useState(0);

  const is_super = currentRole === "super_admin";
  const user_fullname = currentUser ? `${currentUser.fname || ''} ${currentUser.lname || ''}`.trim() : "หัวหน้าฝ่ายปฏิบัติการ";
  const depart_name = currentUser?.department || "Ceremony Operations & Production Division";
  const role_banner_text = is_super ? "เห็นเมนูหัวหน้าฝ่ายปฏิบัติการ" : "หัวหน้าฝ่ายปฏิบัติการ";

  useEffect(() => {
    async function loadStats() {
      const res = await api.getDocuments({}, currentRole);
      if (res.success) {
        const count = res.data.filter(d => !d.assignedToId || d.coordinator === "ยังไม่กำหนด").length;
        setUnassignedCount(count);
      }
    }
    loadStats();
  }, [currentRole]);

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
            <Link href="/manage_coordinator_operation" className="role-card text-decoration-none">
              <div className="role-icon">
                <i className="bi bi-shield-lock-fill text-primary"></i>
              </div>
              <h3 className="role-title">กำหนดสิทธิ์ผู้ประสานงาน</h3>
              <p className="small text-muted mt-2 mb-0">
                กำหนดสิทธิ์สำหรับการเป็นผู้ประสานงานในโครงการ
              </p>
            </Link>
          </div>

          <div className="col-12 col-sm-6 col-md-4 col-lg-3">
            <Link href="/manage_operation2" className="role-card text-decoration-none">
              <div className="role-icon position-relative d-inline-block">
                <i className="bi bi-person-badge text-info"></i>
                {unassignedCount > 0 && (
                  <span
                    className="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                    style={{ fontSize: "0.75rem" }}
                  >
                    {unassignedCount}
                  </span>
                )}
              </div>
              <h3 className="role-title">มอบหมายงานและอุปกรณ์</h3>
              <p className="small text-muted mt-2 mb-0">
                มอบหมายผู้ประสานงาน จัดชุดปฏิบัติงาน และยืม-คืนอุปกรณ์
              </p>
            </Link>
          </div>

          <div className="col-12 col-sm-6 col-md-4 col-lg-3">
            <Link href="/manage_team" className="role-card text-decoration-none">
              <div className="role-icon">
                <i className="bi bi-people-fill text-success"></i>
              </div>
              <h3 className="role-title">จัดชุดปฏิบัติงาน</h3>
              <p className="small text-muted mt-2 mb-0">
                สร้างและบริหารจัดการชุดเจ้าหน้าที่สำหรับลงพื้นที่
              </p>
            </Link>
          </div>

          <div className="col-12 col-sm-6 col-md-4 col-lg-3">
            <Link href="/view_system_logs" className="role-card text-decoration-none">
              <div className="role-icon">
                <i className="bi bi-clock-history text-warning"></i>
              </div>
              <h3 className="role-title">ประวัติการทำงานของระบบ</h3>
              <p className="small text-muted mt-2 mb-0">
                แสดงรายการประวัติการมอบหมายงานและบันทึกกิจกรรม
              </p>
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}

