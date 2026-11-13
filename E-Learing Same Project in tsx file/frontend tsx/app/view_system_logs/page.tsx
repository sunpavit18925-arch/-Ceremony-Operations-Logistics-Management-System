"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";

export default function ViewSystemLogsPage() {
  const { currentRole } = useDemo();
  const [logs, setLogs] = useState([]);
  const [searchQuery, setSearchQuery] = useState("");
  const [loading, setLoading] = useState(true);

  const fetchLogs = async () => {
    try {
      const res = await fetch("http://localhost:4000/api/demo/logs", {
        headers: { "x-demo-role": currentRole }
      });
      const data = await res.json();
      if (data.success) {
        setLogs(data.data);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLogs();
  }, [currentRole]);

  const filteredLogs = logs.filter(l => {
    if (!searchQuery) return true;
    const q = searchQuery.toLowerCase();
    return (
      l.action?.toLowerCase().includes(q) ||
      l.detail?.toLowerCase().includes(q) ||
      l.user?.toLowerCase().includes(q)
    );
  });

  const getActionBadge = (action) => {
    if (action.includes("CREATE") || action.includes("ADD")) {
      return "bg-success";
    }
    if (action.includes("UPDATE") || action.includes("EDIT") || action.includes("ASSIGN")) {
      return "bg-primary";
    }
    if (action.includes("DELETE") || action.includes("REMOVE") || action.includes("REVOKE")) {
      return "bg-danger";
    }
    if (action.includes("BORROW")) {
      return "bg-warning text-dark";
    }
    return "bg-secondary";
  };

  return (
    <div className="content-wrapper" style={{ backgroundColor: "var(--app-muted-bg)", minHeight: "100vh" }}>
      {/* Page Header matching PHP view_system_logs.php */}
      <div
        className="page-header text-center"
        style={{
          background: "linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)",
          color: "white",
          padding: "2.5rem 0",
          marginBottom: "-3rem",
          borderRadius: "0 0 1.5rem 1.5rem",
          boxShadow: "0 4px 15px rgba(0,0,0,0.1)"
        }}
      >
        <div className="container position-relative">
          <Link
            href="/home_head_operation"
            className="btn btn-outline-light rounded-pill position-absolute start-0 top-0 d-inline-flex align-items-center mt-1 ms-2 ms-md-0"
          >
            <i className="bi bi-arrow-left me-2"></i>ย้อนกลับ
          </Link>
          <h2 className="fw-bold mb-2 pt-4 pt-md-0">
            <i className="bi bi-clock-history me-2"></i>ประวัติการทำงานของระบบ
          </h2>
          <p className="text-white-50 mb-0">ตรวจสอบบันทึกความเปลี่ยนแปลงและการดำเนินงานย้อนหลังทั้งหมด</p>
        </div>
      </div>

      <div className="container mt-5 pt-3">
        <div className="card custom-card" style={{ borderRadius: "1.25rem", border: "none", boxShadow: "0 0.5rem 1.5rem rgba(0,0,0,0.08)" }}>
          <div className="card-header bg-white border-bottom-0 pt-4 px-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
              <h5 className="fw-bold text-dark mb-0 d-inline-block me-2">รายการอัปเดตข้อมูล</h5>
              <span className="badge bg-primary rounded-pill align-text-bottom">
                ทั้งหมด {filteredLogs.length} รายการ
              </span>
            </div>
            <div className="position-relative" style={{ minWidth: "280px" }}>
              <span className="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                <i className="bi bi-search"></i>
              </span>
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="form-control ps-5 py-2"
                style={{ borderRadius: "0.75rem", border: "1px solid var(--app-border)" }}
                placeholder="ค้นหาประวัติ..."
              />
            </div>
          </div>

          <div className="card-body p-0">
            <div className="table-responsive">
              <table className="table table-custom table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th style={{ width: "20%" }}>วันที่ / เวลา</th>
                    <th style={{ width: "15%" }}>ประเภท</th>
                    <th style={{ width: "45%" }}>การกระทำ / รายละเอียด</th>
                    <th style={{ width: "20%" }}>ผู้ดำเนินการ</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredLogs.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="text-center py-5 text-muted">
                        <i className="bi bi-clock-history fs-1 d-block mb-2 text-secondary"></i>
                        ไม่พบบันทึกประวัติระบบ
                      </td>
                    </tr>
                  ) : (
                    filteredLogs.map(log => (
                      <tr key={log.id}>
                        <td className="small text-muted">
                          {new Date(log.timestamp).toLocaleString("th-TH")}
                        </td>
                        <td>
                          <span className={`badge ${getActionBadge(log.action)}`}>
                            {log.action}
                          </span>
                        </td>
                        <td>
                          <div className="fw-semibold text-dark small">{log.detail}</div>
                        </td>
                        <td>
                          <div className="small text-muted d-flex align-items-center gap-1">
                            <i className="bi bi-person-fill text-primary"></i>
                            {log.user}
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}


