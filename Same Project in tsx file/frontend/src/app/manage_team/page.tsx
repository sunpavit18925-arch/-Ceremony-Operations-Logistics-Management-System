"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";

export default function ManageTeamPage() {
  const { currentRole } = useDemo();
  const [teams, setTeams] = useState([]);
  const [staffUsers, setStaffUsers] = useState([]);
  const [searchQuery, setSearchQuery] = useState("");
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showAssignModal, setShowAssignModal] = useState(false);
  const [activeSet, setActiveSet] = useState("");
  const [newSetName, setNewSetName] = useState("");
  const [selectedStaffName, setSelectedStaffName] = useState("");
  const [alertMsg, setAlertMsg] = useState(null);

  const fetchTeamsAndStaff = useCallback(() => {
    const load = async () => {
      const headers = { "x-demo-role": currentRole };
      const [teamRes, userRes] = await Promise.all([
        fetch("http://localhost:4000/api/demo/teams", { headers }),
        fetch("http://localhost:4000/api/demo/users", { headers })
      ]);

      const teamData = await teamRes.json();
      const userData = await userRes.json();
      return { teamData, userData };
    };
    return load().then(({ teamData, userData }) => {
      if (teamData.success) setTeams(teamData.data);
      if (userData.success) {
        setStaffUsers(userData.data.filter(u => u.role === "staff_operation"));
      }
    }).catch((err) => {
      console.error(err);
    });
  }, [currentRole]);

  useEffect(() => {
    fetchTeamsAndStaff();
  }, [fetchTeamsAndStaff]);

  const handleCreateSet = async (e) => {
    e.preventDefault();
    if (!newSetName.trim()) return;

    try {
      const res = await fetch("http://localhost:4000/api/demo/teams", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({ setName: newSetName.trim() })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg(`สร้างชุดปฏิบัติการ ${newSetName} สำเร็จ`);
        setNewSetName("");
        setShowCreateModal(false);
        fetchTeamsAndStaff();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  const handleAssignStaff = async (e) => {
    e.preventDefault();
    if (!activeSet || !selectedStaffName) return;

    try {
      const res = await fetch("http://localhost:4000/api/demo/teams/assign", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({
          setName: activeSet,
          staffName: selectedStaffName
        })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg(`เพิ่ม ${selectedStaffName} เข้า ${activeSet} สำเร็จ`);
        setSelectedStaffName("");
        setShowAssignModal(false);
        fetchTeamsAndStaff();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  const handleRemoveStaff = async (setName, staffName) => {
    if (!window.confirm(`ยืนยันการลบ ${staffName} ออกจาก ${setName} ใช่หรือไม่?`)) {
      return;
    }

    try {
      const res = await fetch("http://localhost:4000/api/demo/teams/remove", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({ setName, staffName })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg(`ลบ ${staffName} ออกจาก ${setName} เรียบร้อย`);
        fetchTeamsAndStaff();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  const filteredTeams = teams.filter(t => {
    if (!searchQuery) return true;
    const q = searchQuery.toLowerCase();
    const matchName = t.setName.toLowerCase().includes(q);
    const matchMember = t.members.some(m => m.toLowerCase().includes(q));
    return matchName || matchMember;
  });

  return (
    <div className="content-wrapper" style={{ backgroundColor: "var(--app-page-bg)", minHeight: "100vh", padding: "2rem" }}>
      <div className="container" style={{ maxWidth: "1400px" }}>
        {/* Back Link */}
        <Link
          href="/home_head_operation"
          className="d-inline-flex align-items-center gap-2 mb-4 text-secondary text-decoration-none fw-medium"
        >
          <i className="bi bi-arrow-left-circle-fill fs-5 text-primary"></i>
          <span>กลับสู่หน้าหลัก</span>
        </Link>

        {alertMsg && (
          <div className="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center alert-dismissible">
            <i className="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
            <div><h6 className="mb-0 fw-bold">{alertMsg}</h6></div>
            <button type="button" className="btn-close ms-auto" onClick={() => setAlertMsg(null)}></button>
          </div>
        )}

        {/* Header */}
        <header className="mb-4 d-flex flex-column flex-md-row md-items-center justify-content-between gap-3">
          <div className="d-flex align-items-center gap-3">
            <div style={{ height: "40px", width: "8px", backgroundColor: "#2563eb", borderRadius: "9999px" }}></div>
            <h1 className="fw-bolder text-dark mb-0 fs-2">จัดชุดไปปฏิบัติงาน</h1>
          </div>
          <button
            type="button"
            onClick={() => setShowCreateModal(true)}
            className="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 fw-bold px-4 py-2"
            style={{ borderRadius: "0.75rem", boxShadow: "0 4px 6px -1px rgba(37, 99, 235, 0.2)" }}
          >
            <i className="bi bi-folder-plus fs-5"></i> เพิ่มชุดปฏิบัติงาน
          </button>
        </header>

        {/* Main Table Card */}
        <div className="card shadow-sm border-0" style={{ borderRadius: "1rem", overflow: "hidden" }}>
          <div className="card-header bg-light border-bottom p-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <div className="d-flex align-items-center gap-3">
              <h5 className="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i className="bi bi-table text-primary"></i>
                รายชื่อชุดปฏิบัติงานส่วนกลาง
              </h5>
              <span className="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-bold">
                รวมทั้งหมด {teams.length} ชุด
              </span>
            </div>

            {/* Search Input */}
            <div className="position-relative w-100" style={{ maxWidth: "320px" }}>
              <span className="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                <i className="bi bi-search"></i>
              </span>
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="form-control ps-5 py-2"
                style={{ borderRadius: "0.75rem", border: "1px solid var(--app-border)" }}
                placeholder="ค้นหาชื่อชุด หรือ ชื่อบุคคล..."
              />
            </div>
          </div>

          <div className="table-responsive">
            <table className="table align-middle mb-0">
              <thead style={{ backgroundColor: "#172430", color: "#ffffff" }}>
                <tr>
                  <th style={{ width: "35%", padding: "1rem 2rem", borderRight: "1px solid #334155" }}>
                    ชื่อชุดปฏิบัติงาน
                  </th>
                  <th style={{ width: "65%", padding: "1rem 2rem" }}>
                    รายชื่อบุคลากรในชุด
                  </th>
                </tr>
              </thead>
              <tbody>
                {filteredTeams.length === 0 ? (
                  <tr>
                    <td colSpan={2} className="text-center py-5 text-muted">
                      <i className="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                      ยังไม่มีข้อมูลชุดปฏิบัติงาน ลองสร้างชุดงานด้วยปุ่มด้านบน
                    </td>
                  </tr>
                ) : (
                  filteredTeams.map((team) => (
                    <tr key={team.setName} style={{ borderBottom: "1px solid var(--app-border)" }}>
                      <td className="align-top" style={{ padding: "1.5rem 2rem", borderRight: "1px solid var(--app-border)" }}>
                        <div className="fw-bold fs-5 text-primary mb-3">{team.setName}</div>
                        <button
                          type="button"
                          onClick={() => { setActiveSet(team.setName); setShowAssignModal(true); }}
                          className="btn btn-sm text-white fw-bold d-inline-flex align-items-center gap-2"
                          style={{ backgroundColor: "#10b981", borderRadius: "0.5rem", padding: "0.5rem 1rem" }}
                        >
                          <i className="bi bi-person-plus-fill"></i> เพิ่มคนเข้าชุดนี้
                        </button>
                      </td>
                      <td style={{ padding: "1.5rem 2rem" }}>
                        {team.members.length === 0 ? (
                          <div className="text-muted small">
                            <span className="badge bg-warning text-dark me-2">รอระบุบุคคล</span>
                            ยังไม่มีบุคลากรในชุดนี้ คลิกปุ่มด้านซ้ายเพื่อเพิ่มคน
                          </div>
                        ) : (
                          <div className="d-flex flex-wrap gap-2">
                            {team.members.map((member, idx) => (
                              <div
                                key={idx}
                                className="d-inline-flex align-items-center gap-2 bg-light border px-3 py-2 rounded-3 shadow-sm"
                              >
                                <i className="bi bi-person-circle text-primary"></i>
                                <span className="small fw-semibold text-dark">{member}</span>
                                <button
                                  type="button"
                                  onClick={() => handleRemoveStaff(team.setName, member)}
                                  className="btn btn-sm btn-link text-danger p-0 ms-2"
                                  title="ลบออกจากชุด"
                                >
                                  <i className="bi bi-x-circle-fill"></i>
                                </button>
                              </div>
                            ))}
                          </div>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Modal Create Set */}
      {showCreateModal && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1rem" }}>
              <form onSubmit={handleCreateSet}>
                <div className="modal-header border-bottom bg-light px-4 py-3">
                  <h5 className="modal-title fw-bold text-dark">
                    <i className="bi bi-folder-plus text-primary me-2"></i>สร้างชุดปฏิบัติงานใหม่
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setShowCreateModal(false)}></button>
                </div>
                <div className="modal-body p-4">
                  <label className="form-label small fw-bold text-secondary">ชื่อชุดปฏิบัติงาน (เช่น ชุดที่ 4 หรือ ชุดเฉพาะกิจ A):</label>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="กรอกชื่อชุด..."
                    value={newSetName}
                    onChange={(e) => setNewSetName(e.target.value)}
                    required
                  />
                </div>
                <div className="modal-footer bg-light border-0">
                  <button type="button" className="btn btn-secondary rounded-pill px-3" onClick={() => setShowCreateModal(false)}>
                    ยกเลิก
                  </button>
                  <button type="submit" className="btn btn-primary rounded-pill px-4">
                    สร้างชุด
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Modal Assign Staff */}
      {showAssignModal && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1rem" }}>
              <form onSubmit={handleAssignStaff}>
                <div className="modal-header border-bottom bg-light px-4 py-3">
                  <h5 className="modal-title fw-bold text-dark">
                    <i className="bi bi-person-plus-fill text-success me-2"></i>เพิ่มบุคลากรเข้า {activeSet}
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setShowAssignModal(false)}></button>
                </div>
                <div className="modal-body p-4">
                  <label className="form-label small fw-bold text-secondary">เลือกเจ้าหน้าที่ฝ่ายปฏิบัติการ:</label>
                  <select
                    className="form-select mb-3"
                    value={selectedStaffName}
                    onChange={(e) => setSelectedStaffName(e.target.value)}
                    required
                  >
                    <option value="" disabled>-- คลิกเลือกเจ้าหน้าที่ --</option>
                    {staffUsers.map(st => (
                      <option key={st.id} value={st.name}>
                        {st.name} ({st.department})
                      </option>
                    ))}
                  </select>
                </div>
                <div className="modal-footer bg-light border-0">
                  <button type="button" className="btn btn-secondary rounded-pill px-3" onClick={() => setShowAssignModal(false)}>
                    ยกเลิก
                  </button>
                  <button type="submit" className="btn btn-success rounded-pill px-4">
                    เพิ่มเข้าชุด
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}


