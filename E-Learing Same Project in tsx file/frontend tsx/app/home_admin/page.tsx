"use client";

import React from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";
import SystemBrand from "@/components/branding/SystemBrand";

export default function HomeAdminPage() {
  const { currentUser, currentRole } = useDemo();
  const is_super = currentRole === "super_admin";
  const user_fullname = currentUser ? `${currentUser.fname || ''} ${currentUser.lname || ''}`.trim() : "ผู้ดูแลระบบ";
  const depart_name = currentUser?.department || "ศูนย์เทคโนโลยีสารสนเทศ";
  const role_banner_text = is_super ? "เห็นเมนูผู้ดูแลระบบ" : "ผู้ดูแลระบบ";

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
            <Link href="/admin" className="role-card text-decoration-none">
              <div className="role-icon d-flex justify-content-center align-items-center gap-2 mb-2">
                <i className="bi bi-person-gear text-warning"></i>
              </div>
              <h3 className="role-title">กำหนดสิทธิ์</h3>
              <p className="small text-muted mt-2 mb-0">
                จัดการสิทธิ์การเข้าถึงระบบ, กำหนดบทบาทผู้ใช้งานและเจ้าหน้าที่
              </p>
            </Link>
          </div>

          <div className="col-12 col-sm-6 col-md-4 col-lg-3">
            <Link href="/admin2" className="role-card text-decoration-none">
              <div className="role-icon d-flex justify-content-center align-items-center gap-2 mb-2">
                <i className="bi bi-envelope-download text-success"></i>
                <i className="bi bi-file-earmark-arrow-down text-info"></i>
              </div>
              <h3 className="role-title">รับหนังสือ</h3>
              <p className="small text-muted mt-2 mb-0">
                ลงทะเบียนรับหนังสือเข้า, ตรวจสอบเอกสารและเลขที่รับสารบรรณ
              </p>
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}

