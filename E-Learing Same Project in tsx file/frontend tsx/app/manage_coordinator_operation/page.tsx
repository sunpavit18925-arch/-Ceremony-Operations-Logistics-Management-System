"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";

export default function ManageCoordinatorOperationPage() {
  const { currentUser, currentRole } = useDemo();
  const [users, setUsers] = useState([]);
  const [logs, setLogs] = useState([]);
  const [selectedStaffId, setSelectedStaffId] = useState("");
  const [searchQuery, setSearchQuery] = useState("");
  const [alertMsg, setAlertMsg] = useState(null);
  const [alertType, setAlertType] = useState("success");
  const [showHistoryModal, setShowHistoryModal] = useState(false);
  const [loading, setLoading] = useState(true);

  const fetchUsers = async () => {
    try {
      const res = await fetch("http://localhost:4000/api/demo/users", {
        headers: { "x-demo-role": currentRole }
      });
      const data = await res.json();
      if (data.success) {
        setUsers(data.data);
      }
    } catch (err) {
      console.error(err);
    }
  };

  const fetchLogs = async () => {
    try {
      const res = await fetch("http://localhost:4000/api/demo/logs", {
        headers: { "x-demo-role": currentRole }
      });
      const data = await res.json();
      if (data.success) {
        setLogs(data.data.filter(l => l.action === "ASSIGN_COORD" || l.action === "REVOKE_COORD" || l.action === "ASSIGN_COORDINATOR"));
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUsers();
    fetchLogs();
  }, [currentRole]);

  // Candidates for coordinator: staff_operation
  const candidateStaff = users.filter(u => u.role === "staff_operation");

  // Current coordinators: coordinator_operation
  const coordinators = users.filter(u => u.role === "coordinator_operation");

  const filteredCoordinators = coordinators.filter(c => 
    c.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    c.department?.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const handleAssignCoordinator = async (e) => {
    e.preventDefault();
    if (!selectedStaffId) return;

    try {
      const res = await fetch("http://localhost:4000/api/demo/coordinators/assign", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({ staffId: selectedStaffId })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg("มอบหมายสิทธิ์ผู้ประสานงานสำเร็จ!");
        setAlertType("success");
        setSelectedStaffId("");
        fetchUsers();
        fetchLogs();
      } else {
        setAlertMsg(data.error || "เกิดข้อผิดพลาด");
        setAlertType("danger");
      }
    } catch (err) {
      setAlertMsg("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
      setAlertType("danger");
    }
  };

  const handleRevokeCoordinator = async (userId, name) => {
    if (!window.confirm(`ยืนยันการปลดสิทธิ์คุณ ${name} ออกจากการเป็นผู้ประสานงานใช่หรือไม่?`)) {
      return;
    }

    try {
      const res = await fetch("http://localhost:4000/api/demo/coordinators/revoke", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({ userId })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg("ปลดสิทธิ์ผู้ประสานงานเรียบร้อยแล้ว");
        setAlertType("danger");
        fetchUsers();
        fetchLogs();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  return (
    <div className="content-wrapper" style={{ backgroundColor: "var(--app-page-bg)", minHeight: "100vh" }}>
      {/* Header Section from PHP manage_coordinator_operation.php */}
      <div
        className="page-header text-center"
        style={{
          background: "linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%)",
          color: "white",
          padding: "3rem 0 4rem 0",
          marginBottom: "-3.5rem",
          borderRadius: "0 0 2rem 2rem",
          boxShadow: "0 10px 30px rgba(0,0,0,0.15)",
          position: "relative",
          overflow: "hidden"
        }}
      >
        <div className="container position-relative z-1">
          <div className="row align-items-center">
            <div className="col-12 col-md-2 text-start mb-3 mb-md-0">
              <Link
                href="/home_head_operation"
                className="btn btn-outline-light rounded-pill d-inline-flex align-items-center"
                style={{ fontWeight: 500, padding: "0.4rem 1.2rem", borderColor: "rgba(255,255,255,0.4)" }}
              >
                <i className="bi bi-arrow-left me-2"></i>ย้อนกลับ
              </Link>
            </div>
            <div className="col-12 col-md-8 text-center">
              <h2 className="fw-bold mb-2">
                <i className="bi bi-person-badge-fill me-2 text-warning"></i>กำหนดสิทธิ์ผู้ประสานงาน
              </h2>
              <p className="text-white-50 fs-6 mb-0">กำหนดสิทธิ์ให้บุคลากรในฝ่ายเพื่อทำหน้าที่ประสานงาน</p>
            </div>
            <div className="col-12 col-md-2"></div>
          </div>
        </div>
      </div>

      <div className="container" style={{ position: "relative", zIndex: 2, paddingTop: "4.5rem" }}>
        {/* Alerts */}
        {alertMsg && (
          <div
            className={`alert alert-${alertType} border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center mx-auto`}
            style={{ maxWidth: "800px" }}
          >
            <i className={`bi ${alertType === "success" ? "bi-check-circle-fill text-success" : "bi-person-dash-fill text-danger"} fs-4 me-3`}></i>
            <div>
              <h6 className="mb-0 fw-bold">{alertMsg}</h6>
            </div>
            <button type="button" className="btn-close ms-auto" onClick={() => setAlertMsg(null)}></button>
          </div>
        )}

        <div className="row justify-content-center g-4">
          {/* ฝั่งซ้าย: ฟอร์มกำหนดสิทธิ์ */}
          <div className="col-lg-6">
            <div className="card custom-card h-100 border-top border-4 border-success" style={{ borderRadius: "1.5rem", boxShadow: "0 10px 40px rgba(0,0,0,0.06)", background: "var(--app-surface)" }}>
              <div className="card-body p-4 p-md-5">
                <div className="text-center mb-4">
                  <div
                    className="icon-wrapper bg-success bg-opacity-10 text-success"
                    style={{ width: "80px", height: "80px", borderRadius: "50%", display: "flex", alignItems: "center", justifyContent: "center", margin: "0 auto 1.5rem auto" }}
                  >
                    <i className="bi bi-person-plus-fill fs-1"></i>
                  </div>
                  <h4 className="fw-bold text-dark">มอบหมายผู้ประสานงาน</h4>
                  <p className="text-muted small">เลือกรายชื่อบุคลากรจากฝ่ายปฏิบัติการ (เฉพาะผู้ที่ยังไม่มีสิทธิ์)</p>
                </div>

                <form onSubmit={handleAssignCoordinator}>
                  <div className="mb-4 position-relative">
                    <label className="form-label fw-bold text-secondary ms-2 mb-2">
                      <i className="bi bi-list-ul me-2"></i>รายชื่อบุคลากรฝ่ายปฏิบัติการ
                    </label>
                    <select
                      value={selectedStaffId}
                      onChange={(e) => setSelectedStaffId(e.target.value)}
                      className="form-select form-select-custom"
                      style={{
                        borderRadius: "1rem",
                        border: "2px solid var(--app-border)",
                        padding: "1rem 1.25rem",
                        fontSize: "1.05rem",
                        backgroundColor: "var(--app-muted-bg)"
                      }}
                      required
                    >
                      <option value="" disabled>-- คลิกเพื่อเลือกบุคลากร --</option>
                      {candidateStaff.length === 0 ? (
                        <option value="" disabled>ไม่มีบุคลากรที่สามารถเลือกได้</option>
                      ) : (
                        candidateStaff.map(staff => (
                          <option key={staff.id} value={staff.id}>
                            {staff.name} ({staff.department})
                          </option>
                        ))
                      )}
                    </select>
                  </div>

                  <div className="d-grid mt-5">
                    <button
                      type="submit"
                      disabled={!selectedStaffId}
                      className="btn btn-lg text-white"
                      style={{
                        background: "linear-gradient(135deg, #11998e 0%, #38ef7d 100%)",
                        border: "none",
                        padding: "0.8rem 2rem",
                        borderRadius: "1rem",
                        fontWeight: 600,
                        letterSpacing: "0.5px",
                        boxShadow: "0 8px 20px rgba(56, 239, 125, 0.3)"
                      }}
                    >
                      <i className="bi bi-check2-circle me-2"></i> ยืนยันการมอบหมายสิทธิ์
                    </button>
                  </div>

                  <div className="text-center mt-4">
                    <button
                      type="button"
                      onClick={() => setShowHistoryModal(true)}
                      className="btn btn-link text-decoration-none text-muted fw-medium"
                    >
                      <i className="bi bi-clock-history me-1"></i> ดูประวัติการมอบหมายผู้ประสานงานทั้งหมด
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          {/* ฝั่งขวา: รายชื่อผู้ประสานงานปัจจุบัน */}
          <div className="col-lg-6">
            <div className="card custom-card h-100 border-top border-4" style={{ borderColor: "#2c5364", borderRadius: "1.5rem", boxShadow: "0 10px 40px rgba(0,0,0,0.06)", background: "var(--app-surface)" }}>
              <div className="card-body p-4 p-md-5 bg-light rounded-bottom-4">
                <div className="d-flex align-items-center justify-content-between mb-4">
                  <h5 className="fw-bold mb-0" style={{ color: "var(--app-heading)" }}>
                    <i className="bi bi-star-fill text-warning me-2"></i>ผู้ประสานงานปัจจุบัน
                  </h5>
                  <span className="badge rounded-pill bg-dark bg-opacity-10 text-dark px-3 py-2">
                    {coordinators.length} ท่าน
                  </span>
                </div>

                <div className="mb-4 position-relative">
                  <span className="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                    <i className="bi bi-search"></i>
                  </span>
                  <input
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="form-control bg-white shadow-sm ps-5 py-2"
                    style={{ borderRadius: "1rem", border: "1px solid var(--app-border)" }}
                    placeholder="ค้นหาชื่อผู้ประสานงาน..."
                  />
                </div>

                <div style={{ minHeight: "300px" }}>
                  {filteredCoordinators.length === 0 ? (
                    <div className="text-center py-5">
                      <i className="bi bi-people text-muted fs-1 opacity-50 mb-3 d-block"></i>
                      <h6 className="text-muted fw-bold">ไม่พบรายชื่อผู้ประสานงาน</h6>
                      <p className="text-muted small">โปรดเลือกบุคลากรจากเมนูด้านซ้ายเพื่อกำหนดสิทธิ์</p>
                    </div>
                  ) : (
                    filteredCoordinators.map(coord => {
                      const initials = (coord.fname?.[0] || "") + (coord.lname?.[0] || "");
                      return (
                        <div
                          key={coord.id}
                          className="coordinator-item shadow-sm mb-3 d-flex align-items-center justify-content-between"
                          style={{
                            background: "var(--app-surface)",
                            border: "1px solid var(--app-border)",
                            borderRadius: "1.25rem",
                            padding: "1.25rem"
                          }}
                        >
                          <div className="d-flex align-items-center gap-3">
                            <div
                              style={{
                                width: "50px",
                                height: "50px",
                                background: "linear-gradient(135deg, #2c5364, #203a43)",
                                color: "white",
                                borderRadius: "50%",
                                display: "flex",
                                alignItems: "center",
                                justifyContent: "center",
                                fontSize: "1.2rem",
                                fontWeight: "bold",
                                boxShadow: "0 4px 10px rgba(44, 83, 100, 0.2)"
                              }}
                            >
                              {initials || "ผป"}
                            </div>
                            <div>
                              <h6 className="fw-bold mb-1 text-dark">{coord.name}</h6>
                              <span
                                className="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2 py-1"
                                style={{ fontSize: "0.7rem" }}
                              >
                                <i className="bi bi-shield-check me-1"></i>สิทธิ์: ผู้ประสานงาน
                              </span>
                            </div>
                          </div>

                          <button
                            type="button"
                            onClick={() => handleRevokeCoordinator(coord.id, coord.name)}
                            className="btn btn-sm"
                            style={{
                              backgroundColor: "var(--bs-danger-bg-subtle)",
                              color: "var(--bs-danger-text-emphasis)",
                              border: "1px solid var(--bs-danger-border-subtle)",
                              borderRadius: "0.75rem",
                              padding: "0.4rem 1rem",
                              fontWeight: 500,
                              fontSize: "0.9rem"
                            }}
                          >
                            <i className="bi bi-x-circle me-1"></i>ปลดสิทธิ์
                          </button>
                        </div>
                      );
                    })
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* History Modal */}
      {showHistoryModal && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered modal-lg">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <div className="modal-header border-bottom bg-light px-4 py-3">
                <h5 className="modal-title fw-bold text-dark">
                  <i className="bi bi-clock-history me-2 text-primary"></i>ประวัติการมอบหมายผู้ประสานงาน
                </h5>
                <button type="button" className="btn-close" onClick={() => setShowHistoryModal(false)}></button>
              </div>
              <div className="modal-body p-4" style={{ maxHeight: "60vh", overflowY: "auto" }}>
                {logs.length === 0 ? (
                  <div className="text-center py-4 text-muted">ยังไม่มีประวัติการบันทึก</div>
                ) : (
                  <table className="table table-hover align-middle">
                    <thead className="table-light">
                      <tr>
                        <th>วันที่ - เวลา</th>
                        <th>ประเภท</th>
                        <th>รายละเอียด</th>
                        <th>ดำเนินการโดย</th>
                      </tr>
                    </thead>
                    <tbody>
                      {logs.map(log => (
                        <tr key={log.id}>
                          <td className="small text-muted">{new Date(log.timestamp).toLocaleString("th-TH")}</td>
                          <td>
                            <span className={`badge ${log.action.includes("ASSIGN") ? "bg-success" : "bg-danger"}`}>
                              {log.action}
                            </span>
                          </td>
                          <td className="small fw-semibold">{log.detail}</td>
                          <td className="small text-muted">{log.user}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
              <div className="modal-footer bg-light border-0">
                <button type="button" className="btn btn-secondary rounded-pill px-4" onClick={() => setShowHistoryModal(false)}>
                  ปิดหน้าต่าง
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}


