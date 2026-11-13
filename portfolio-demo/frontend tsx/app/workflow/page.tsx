"use client";

import React from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";

export default function WorkflowPage() {
  const { switchRole } = useDemo();

  return (
    <div>
      <div className="page-header text-center" style={{ background: "linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)", color: "white", padding: "2rem 0", marginBottom: "2rem" }}>
        <div className="container">
          <h2 className="fw-bold mb-1">
            <i className="bi bi-diagram-3 me-2"></i>
            ผังกระบวนการดำเนินงานระบบพลับพลาพิธี
          </h2>
          <p className="mb-0 text-white-50">
            ขั้นตอนการทำงานจริงตั้งแต่ลงรับหนังสือราชการจนถึงจัดส่งยานพาหนะและปิดภารกิจ
          </p>
        </div>
      </div>

      <div className="container">
        <div className="card custom-card p-4 mb-4">
          <div className="row g-4 text-center">
            <div className="col-12 col-md">
              <div className="p-3 border rounded-3 bg-light h-100">
                <div className="fs-1 text-primary mb-2"><i className="bi bi-file-earmark-arrow-down"></i></div>
                <h6 className="fw-bold">1. ลงรับหนังสือ</h6>
                <small className="text-muted d-block mb-2">ฝ่ายสารบรรณ (Admin)</small>
                <button className="btn btn-sm btn-outline-primary" onClick={() => switchRole("admin")}>
                  สลับเป็น Admin
                </button>
              </div>
            </div>

            <div className="col-auto d-none d-md-flex align-items-center text-muted fs-4">→</div>

            <div className="col-12 col-md">
              <div className="p-3 border rounded-3 bg-light h-100">
                <div className="fs-1 text-success mb-2"><i className="bi bi-person-check"></i></div>
                <h6 className="fw-bold">2. มอบหมายงาน</h6>
                <small className="text-muted d-block mb-2">หัวหน้าฝ่ายปฏิบัติการ</small>
                <button className="btn btn-sm btn-outline-success" onClick={() => switchRole("head_operation")}>
                  สลับเป็น หน.ปฏิบัติการ
                </button>
              </div>
            </div>

            <div className="col-auto d-none d-md-flex align-items-center text-muted fs-4">→</div>

            <div className="col-12 col-md">
              <div className="p-3 border rounded-3 bg-light h-100">
                <div className="fs-1 text-info mb-2"><i className="bi bi-truck"></i></div>
                <h6 className="fw-bold">3. จัดสรรยานพาหนะ</h6>
                <small className="text-muted d-block mb-2">หัวหน้าฝ่ายยานยนต์</small>
                <button className="btn btn-sm btn-outline-info" onClick={() => switchRole("head_vehicle")}>
                  สลับเป็น หน.ยานยนต์
                </button>
              </div>
            </div>

            <div className="col-auto d-none d-md-flex align-items-center text-muted fs-4">→</div>

            <div className="col-12 col-md">
              <div className="p-3 border rounded-3 bg-light h-100">
                <div className="fs-1 text-warning mb-2"><i className="bi bi-box-seam"></i></div>
                <h6 className="fw-bold">4. เบิกยืมอุปกรณ์</h6>
                <small className="text-muted d-block mb-2">ฝ่ายปฏิบัติการ</small>
                <button className="btn btn-sm btn-outline-warning text-dark" onClick={() => switchRole("coordinator_operation")}>
                  สลับเป็น ผู้ประสานงาน
                </button>
              </div>
            </div>

            <div className="col-auto d-none d-md-flex align-items-center text-muted fs-4">→</div>

            <div className="col-12 col-md">
              <div className="p-3 border rounded-3 bg-light h-100">
                <div className="fs-1 text-danger mb-2"><i className="bi bi-check2-circle"></i></div>
                <h6 className="fw-bold">5. เสร็จสิ้นภารกิจ</h6>
                <small className="text-muted d-block mb-2">ส่งคืนอุปกรณ์ & ปิดงาน</small>
                <Link href="/documents" className="btn btn-sm btn-outline-dark">
                  ดูรายการงาน
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
