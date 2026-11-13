"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";
import { api } from "@/services/api";

export default function DashboardPage() {
  const { currentRole } = useDemo();
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  const fetchStats = async () => {
    setLoading(true);
    const res = await api.getDashboard(currentRole);
    if (res.success) {
      setStats(res.data);
    }
    setLoading(false);
  };

  useEffect(() => {
    fetchStats();
    const handleReset = () => fetchStats();
    window.addEventListener("demo-data-reset", handleReset);
    return () => window.removeEventListener("demo-data-reset", handleReset);
  }, [currentRole]);

  return (
    <div>
      <div className="page-header text-center" style={{ background: "linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)", color: "white", padding: "2rem 0", marginBottom: "2rem" }}>
        <div className="container">
          <h2 className="fw-bold mb-1">
            <i className="bi bi-speedometer2 me-2"></i>
            แดชบอร์ดสรุปภาพรวมระบบงาน
          </h2>
          <p className="mb-0 text-white-50">
            ระบบบริหารจัดการพิธีการและโลจิสติกส์
          </p>
        </div>
      </div>

      <div className="container">
        {/* KPI Counter Cards */}
        <div className="row g-3 mb-4">
          <div className="col-12 col-sm-6 col-lg-3">
            <div className="card custom-card p-3 border-start border-primary border-4">
              <div className="d-flex justify-content-between align-items-center">
                <div>
                  <span className="text-muted small">หนังสือราชการทั้งหมด</span>
                  <h3 className="fw-bold text-dark m-0">{stats?.totalDocs || 0}</h3>
                </div>
                <div className="fs-2 text-primary opacity-50"><i className="bi bi-file-earmark-text"></i></div>
              </div>
            </div>
          </div>

          <div className="col-12 col-sm-6 col-lg-3">
            <div className="card custom-card p-3 border-start border-warning border-4">
              <div className="d-flex justify-content-between align-items-center">
                <div>
                  <span className="text-muted small">รอมอบหมาย / ดำเนินการ</span>
                  <h3 className="fw-bold text-dark m-0">{(stats?.pendingDocs || 0) + (stats?.assignedDocs || 0)}</h3>
                </div>
                <div className="fs-2 text-warning opacity-50"><i className="bi bi-hourglass-split"></i></div>
              </div>
            </div>
          </div>

          <div className="col-12 col-sm-6 col-lg-3">
            <div className="card custom-card p-3 border-start border-success border-4">
              <div className="d-flex justify-content-between align-items-center">
                <div>
                  <span className="text-muted small">เสร็จสิ้นภารกิจ</span>
                  <h3 className="fw-bold text-dark m-0">{stats?.completedDocs || 0}</h3>
                </div>
                <div className="fs-2 text-success opacity-50"><i className="bi bi-check-circle"></i></div>
              </div>
            </div>
          </div>

          <div className="col-12 col-sm-6 col-lg-3">
            <div className="card custom-card p-3 border-start border-info border-4">
              <div className="d-flex justify-content-between align-items-center">
                <div>
                  <span className="text-muted small">ยานพาหนะพร้อมใช้</span>
                  <h3 className="fw-bold text-dark m-0">{stats?.activeVehicles || 0}</h3>
                </div>
                <div className="fs-2 text-info opacity-50"><i className="bi bi-truck"></i></div>
              </div>
            </div>
          </div>
        </div>

        {/* Audit Log Table matching PHP view_system_logs.php */}
        <div className="card custom-card">
          <div className="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 className="m-0 fw-bold text-dark">
              <i className="bi bi-clock-history text-primary me-2"></i>
              ประวัติการดำเนินงานล่าสุดในระบบ (Audit Trail)
            </h6>
            <button className="btn btn-sm btn-outline-secondary" onClick={fetchStats}>
              <i className="bi bi-arrow-clockwise"></i> รีเฟรช
            </button>
          </div>
          <div className="table-responsive">
            <table className="table table-hover table-custom align-middle mb-0">
              <thead>
                <tr>
                  <th style={{ width: "80px" }} className="text-center">#</th>
                  <th>กิจกรรม</th>
                  <th>รายละเอียด</th>
                  <th>ผู้ดำเนินการ</th>
                  <th>วัน-เวลา</th>
                </tr>
              </thead>
              <tbody>
                {stats?.recentLogs && stats.recentLogs.map((log, idx) => (
                  <tr key={log.id}>
                    <td className="text-center text-muted">{idx + 1}</td>
                    <td>
                      <span className="badge bg-light text-dark border font-monospace">
                        {log.action}
                      </span>
                    </td>
                    <td><span className="fw-medium text-dark">{log.detail}</span></td>
                    <td><i className="bi bi-person text-muted me-1"></i>{log.user}</td>
                    <td><small className="text-muted">{new Date(log.timestamp).toLocaleString("th-TH")}</small></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
