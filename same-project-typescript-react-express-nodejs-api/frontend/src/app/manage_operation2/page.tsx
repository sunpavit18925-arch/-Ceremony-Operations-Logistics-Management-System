"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";

export default function ManageOperation2Page() {
  const { currentRole, currentUser } = useDemo();
  const [documents, setDocuments] = useState([]);
  const [users, setUsers] = useState([]);
  const [teamSets, setTeamSets] = useState([]);
  const [equipments, setEquipments] = useState([]);
  const [borrowings, setBorrowings] = useState([]);
  const [loading, setLoading] = useState(true);

  // Filters
  const [searchText, setSearchText] = useState("");
  const [searchDate, setSearchDate] = useState("");
  const [searchType, setSearchType] = useState("");
  const [searchStatus, setSearchStatus] = useState("");

  // Modals
  const [activeDoc, setActiveDoc] = useState(null);
  const [showCoordModal, setShowCoordModal] = useState(false);
  const [showTeamModal, setShowTeamModal] = useState(false);
  const [showEquipModal, setShowEquipModal] = useState(false);
  const [showDetailModal, setShowDetailModal] = useState(false);

  // Form states
  const [selectedCoordId, setSelectedCoordId] = useState("");
  const [selectedTeamName, setSelectedTeamName] = useState("");
  const [selectedEquipCode, setSelectedEquipCode] = useState("");
  const [equipQty, setEquipQty] = useState(1);
  const [alertMsg, setAlertMsg] = useState(null);

  const fetchData = useCallback(() => {
    const load = async () => {
      const headers = { "x-demo-role": currentRole };
      const [docRes, userRes, teamRes, equipRes] = await Promise.all([
        fetch("http://localhost:4000/api/demo/documents", { headers }),
        fetch("http://localhost:4000/api/demo/users", { headers }),
        fetch("http://localhost:4000/api/demo/teams", { headers }),
        fetch("http://localhost:4000/api/demo/equipments", { headers })
      ]);

      const docData = await docRes.json();
      const userData = await userRes.json();
      const teamData = await teamRes.json();
      const equipData = await equipRes.json();
      return { docData, userData, teamData, equipData };
    };
    return load().then(({ docData, userData, teamData, equipData }) => {
      if (docData.success) setDocuments(docData.data);
      if (userData.success) setUsers(userData.data);
      if (teamData.success) setTeamSets(teamData.data);
      if (equipData.success) {
        setEquipments(equipData.data);
        setBorrowings(equipData.borrowings || []);
      }
    }).catch((err) => {
      console.error(err);
    }).finally(() => {
      setLoading(false);
    });
  }, [currentRole]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Coordinators available
  const coordinatorList = users.filter(u => u.role === "coordinator_operation");

  // Filtering
  const filteredDocs = documents.filter(doc => {
    if (searchText) {
      const matchText = doc.docName.toLowerCase().includes(searchText.toLowerCase()) ||
                        doc.docNumber.toLowerCase().includes(searchText.toLowerCase());
      if (!matchText) return false;
    }
    if (searchDate && doc.startDate !== searchDate) {
      return false;
    }
    if (searchType && doc.jobType !== searchType) {
      return false;
    }
    if (searchStatus) {
      if (searchStatus === "unassigned" && doc.assignedToId) return false;
      if (searchStatus === "pending" && (!doc.assignedToId || doc.isAcknowledged)) return false;
      if (searchStatus === "acknowledged" && !doc.isAcknowledged) return false;
      if (searchStatus === "set_assigned" && !doc.teamSet) return false;
    }
    return true;
  });

  const handleAssignCoordinator = async (e) => {
    e.preventDefault();
    if (!activeDoc || !selectedCoordId) return;

    try {
      const res = await fetch(`http://localhost:4000/api/demo/documents/${activeDoc.id}/assign-coordinator`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({ coordinatorId: parseInt(selectedCoordId) })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg("มอบหมายผู้ประสานงานสำเร็จ!");
        setShowCoordModal(false);
        fetchData();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  const handleAssignTeam = async (e) => {
    e.preventDefault();
    if (!activeDoc || !selectedTeamName) return;

    try {
      const res = await fetch(`http://localhost:4000/api/demo/documents/${activeDoc.id}/assign-team`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({ teamSetName: selectedTeamName })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg(`มอบหมายชุดปฏิบัติการ ${selectedTeamName} สำเร็จ!`);
        setShowTeamModal(false);
        fetchData();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  const handleBorrowEquip = async (e) => {
    e.preventDefault();
    if (!activeDoc || !selectedEquipCode || equipQty < 1) return;

    try {
      const res = await fetch("http://localhost:4000/api/demo/equipments/borrow", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({
          docId: activeDoc.id,
          equipmentCode: selectedEquipCode,
          quantity: Number(equipQty)
        })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg(data.message);
        setSelectedEquipCode("");
        setEquipQty(1);
        fetchData();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  const handleReturnEquip = async (borrowingId) => {
    try {
      const res = await fetch(`http://localhost:4000/api/demo/equipments/borrowings/${borrowingId}/return`, {
        method: "POST",
        headers: { "x-demo-role": currentRole }
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg(data.message);
        fetchData();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  const docBorrowings = activeDoc ? borrowings.filter(b => b.docId === activeDoc.id) : [];

  return (
    <div className="content-wrapper" style={{ backgroundColor: "var(--app-muted-bg)", minHeight: "100vh" }}>
      {/* Top Banner Header matching PHP manage_operation2.php */}
      <div
        className="page-header text-center"
        style={{
          background: "linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)",
          color: "white",
          padding: "2.5rem 0",
          marginBottom: "-3rem",
          borderRadius: "0 0 1.5rem 1.5rem",
          boxShadow: "0 4px 15px rgba(0, 0, 0, 0.1)"
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
            <i className="bi bi-person-badge me-2"></i>ระบบมอบหมายงานและอุปกรณ์
          </h2>
          <p className="text-white-50 mb-0">มอบหมายผู้ประสานงาน จัดชุดปฏิบัติงาน และยืม-คืนอุปกรณ์</p>
        </div>
      </div>

      <div className="container mt-5 pt-3">
        {alertMsg && (
          <div className="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center alert-dismissible">
            <i className="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
            <div><h6 className="mb-0 fw-bold">{alertMsg}</h6></div>
            <button type="button" className="btn-close ms-auto" onClick={() => setAlertMsg(null)}></button>
          </div>
        )}

        {/* Filter Card */}
        <div className="card custom-card mb-4" style={{ borderRadius: "1.25rem", border: "none", boxShadow: "0 0.5rem 1.5rem rgba(0,0,0,0.08)" }}>
          <div className="card-body p-4">
            <form onSubmit={(e) => e.preventDefault()} className="row g-3">
              <div className="col-md-3">
                <label className="form-label small fw-bold text-muted">ชื่องาน หรือ รหัสงาน</label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="ค้นหาชื่องาน..."
                  value={searchText}
                  onChange={(e) => setSearchText(e.target.value)}
                />
              </div>
              <div className="col-md-3">
                <label className="form-label small fw-bold text-muted">วันที่ปฏิบัติงาน</label>
                <input
                  type="date"
                  className="form-control"
                  value={searchDate}
                  onChange={(e) => setSearchDate(e.target.value)}
                />
              </div>
              <div className="col-md-3">
                <label className="form-label small fw-bold text-muted">ประเภทงาน</label>
                <select className="form-select" value={searchType} onChange={(e) => setSearchType(e.target.value)}>
                  <option value="">ทั้งหมด</option>
                  <option value="พิธีการ">พิธีการ</option>
                  <option value="วางศิลาฤกษ์">วางศิลาฤกษ์</option>
                  <option value="ประชุมรับฟังความคิดเห็น">ประชุมรับฟังความคิดเห็น</option>
                  <option value="อื่นๆ">อื่นๆ</option>
                </select>
              </div>
              <div className="col-md-3">
                <label className="form-label small fw-bold text-muted">สถานะงาน</label>
                <select className="form-select" value={searchStatus} onChange={(e) => setSearchStatus(e.target.value)}>
                  <option value="">ทั้งหมด</option>
                  <option value="unassigned">ยังไม่มอบหมาย</option>
                  <option value="pending">รอผู้ประสานงานรับทราบ</option>
                  <option value="acknowledged">ผู้ประสานงานรับทราบแล้ว</option>
                  <option value="set_assigned">จัดชุดปฏิบัติงานแล้ว</option>
                </select>
              </div>
            </form>
          </div>
        </div>

        {/* Table Card */}
        <div className="card custom-card" style={{ borderRadius: "1.25rem", border: "none", boxShadow: "0 0.5rem 1.5rem rgba(0,0,0,0.08)" }}>
          <div className="table-responsive">
            <table className="table table-custom table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th style={{ width: "12%" }}>รหัส / เลขที่</th>
                  <th style={{ width: "24%" }}>ชื่องาน / สถานที่</th>
                  <th style={{ width: "12%" }}>ประเภท</th>
                  <th style={{ width: "14%" }}>วันที่ปฏิบัติงาน</th>
                  <th style={{ width: "14%" }}>ผู้ประสานงาน</th>
                  <th style={{ width: "12%" }}>จัดชุดปฏิบัติการ</th>
                  <th style={{ width: "12%" }} className="text-center">จัดการ</th>
                </tr>
              </thead>
              <tbody>
                {filteredDocs.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="text-center py-5 text-muted">
                      <i className="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                      ไม่พบข้อมูลงานที่ค้นหา
                    </td>
                  </tr>
                ) : (
                  filteredDocs.map(doc => (
                    <tr key={doc.id}>
                      <td>
                        <span className="badge bg-light text-dark border fw-bold">{doc.docNumber}</span>
                        <small className="d-block text-muted">{doc.sourceDocNumber}</small>
                      </td>
                      <td>
                        <div className="fw-bold text-dark">{doc.docName}</div>
                        <small className="text-muted"><i className="bi bi-geo-alt-fill text-danger me-1"></i>{doc.venueProvince} ({doc.venueName})</small>
                      </td>
                      <td>
                        <span className={`badge ${doc.jobType === "พิธีการ" ? "bg-warning text-dark" : "bg-primary text-white"}`}>
                          {doc.jobType}
                        </span>
                      </td>
                      <td className="small text-muted">
                        <div><i className="bi bi-calendar3 me-1"></i>{doc.startDate}</div>
                        <div>ถึง {doc.endDate}</div>
                      </td>
                      <td>
                        {doc.assignedToId ? (
                          <div>
                            <div className="fw-semibold text-dark small">{doc.coordinator}</div>
                            {doc.isAcknowledged ? (
                              <span className="badge bg-success bg-opacity-10 text-success rounded-pill px-2" style={{ fontSize: "0.7rem" }}>
                                <i className="bi bi-check-circle me-1"></i>รับทราบแล้ว
                              </span>
                            ) : (
                              <span className="badge bg-warning bg-opacity-10 text-warning rounded-pill px-2" style={{ fontSize: "0.7rem" }}>
                                <i className="bi bi-clock me-1"></i>รอยืนยัน
                              </span>
                            )}
                            <button
                              type="button"
                              onClick={() => { setActiveDoc(doc); setShowCoordModal(true); }}
                              className="btn btn-sm btn-link text-primary p-0 d-block mt-1"
                              style={{ fontSize: "0.75rem" }}
                            >
                              เปลี่ยนผู้ประสานงาน
                            </button>
                          </div>
                        ) : (
                          <div>
                            <span className="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 mb-1" style={{ fontSize: "0.7rem" }}>
                              ยังไม่ได้มอบหมาย
                            </span>
                            <button
                              type="button"
                              onClick={() => { setActiveDoc(doc); setShowCoordModal(true); }}
                              className="btn btn-sm btn-outline-primary rounded-pill px-2 py-0 d-block"
                              style={{ fontSize: "0.75rem" }}
                            >
                              <i className="bi bi-person-plus me-1"></i>มอบหมาย
                            </button>
                          </div>
                        )}
                      </td>
                      <td>
                        {doc.teamSet ? (
                          <div>
                            <span className="badge bg-info bg-opacity-10 text-info border border-info rounded-pill px-2" style={{ fontSize: "0.75rem" }}>
                              <i className="bi bi-people-fill me-1"></i>{doc.teamSet}
                            </span>
                            <button
                              type="button"
                              onClick={() => { setActiveDoc(doc); setShowTeamModal(true); }}
                              className="btn btn-sm btn-link text-secondary p-0 d-block mt-1"
                              style={{ fontSize: "0.72rem" }}
                            >
                              เปลี่ยนชุด
                            </button>
                          </div>
                        ) : (
                          <div>
                            <span className="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2 mb-1" style={{ fontSize: "0.7rem" }}>
                              ยังไม่จัดชุด
                            </span>
                            <button
                              type="button"
                              onClick={() => { setActiveDoc(doc); setShowTeamModal(true); }}
                              className="btn btn-sm btn-outline-success rounded-pill px-2 py-0 d-block"
                              style={{ fontSize: "0.75rem" }}
                            >
                              <i className="bi bi-plus-circle me-1"></i>จัดชุด
                            </button>
                          </div>
                        )}
                      </td>
                      <td className="text-center">
                        <div className="d-flex justify-content-center gap-1">
                          <button
                            type="button"
                            onClick={() => { setActiveDoc(doc); setShowEquipModal(true); }}
                            className="btn btn-sm btn-outline-secondary rounded-pill px-2"
                            title="ยืม/คืนอุปกรณ์"
                            style={{ fontSize: "0.75rem" }}
                          >
                            <i className="bi bi-box-seam me-1"></i>อุปกรณ์
                          </button>
                          <button
                            type="button"
                            onClick={() => { setActiveDoc(doc); setShowDetailModal(true); }}
                            className="btn btn-sm btn-outline-info rounded-pill px-2"
                            title="ดูรายละเอียด"
                            style={{ fontSize: "0.75rem" }}
                          >
                            <i className="bi bi-eye"></i>
                          </button>
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

      {/* Assign Coordinator Modal */}
      {showCoordModal && activeDoc && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <form onSubmit={handleAssignCoordinator}>
                <div className="modal-header border-bottom bg-light px-4 py-3">
                  <h5 className="modal-title fw-bold text-dark">
                    <i className="bi bi-person-check-fill text-primary me-2"></i>มอบหมายผู้ประสานงาน
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setShowCoordModal(false)}></button>
                </div>
                <div className="modal-body p-4">
                  <p className="small text-muted mb-3">
                    ภารกิจ: <strong>{activeDoc.docName}</strong> ({activeDoc.docNumber})
                  </p>
                  <label className="form-label fw-bold small text-secondary">เลือกผู้ประสานงาน:</label>
                  <select
                    className="form-select mb-3"
                    value={selectedCoordId}
                    onChange={(e) => setSelectedCoordId(e.target.value)}
                    required
                  >
                    <option value="" disabled>-- คลิกเลือกผู้ประสานงาน --</option>
                    {coordinatorList.map(coord => (
                      <option key={coord.id} value={coord.id}>
                        {coord.name} ({coord.department})
                      </option>
                    ))}
                  </select>
                </div>
                <div className="modal-footer bg-light border-0">
                  <button type="button" className="btn btn-secondary rounded-pill px-3" onClick={() => setShowCoordModal(false)}>
                    ยกเลิก
                  </button>
                  <button type="submit" className="btn btn-primary rounded-pill px-4">
                    บันทึกการมอบหมาย
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Assign Team Set Modal */}
      {showTeamModal && activeDoc && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <form onSubmit={handleAssignTeam}>
                <div className="modal-header border-bottom bg-light px-4 py-3">
                  <h5 className="modal-title fw-bold text-dark">
                    <i className="bi bi-people-fill text-success me-2"></i>มอบหมายชุดปฏิบัติการ
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setShowTeamModal(false)}></button>
                </div>
                <div className="modal-body p-4">
                  <p className="small text-muted mb-3">
                    ภารกิจ: <strong>{activeDoc.docName}</strong>
                  </p>
                  <label className="form-label fw-bold small text-secondary">เลือกชุดปฏิบัติงานส่วนกลาง:</label>
                  <select
                    className="form-select mb-3"
                    value={selectedTeamName}
                    onChange={(e) => setSelectedTeamName(e.target.value)}
                    required
                  >
                    <option value="" disabled>-- คลิกเลือกชุดปฏิบัติการ --</option>
                    {teamSets.map(team => (
                      <option key={team.setName} value={team.setName}>
                        {team.setName} (สมาชิก: {team.members.join(", ") || "ยังไม่มีสมาชิก"})
                      </option>
                    ))}
                  </select>
                </div>
                <div className="modal-footer bg-light border-0">
                  <button type="button" className="btn btn-secondary rounded-pill px-3" onClick={() => setShowTeamModal(false)}>
                    ยกเลิก
                  </button>
                  <button type="submit" className="btn btn-success rounded-pill px-4">
                    ยืนยันจัดชุด
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Equipment Borrow/Return Modal */}
      {showEquipModal && activeDoc && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered modal-lg">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <div className="modal-header border-bottom bg-light px-4 py-3">
                <h5 className="modal-title fw-bold text-dark">
                  <i className="bi bi-box-seam text-info me-2"></i>จัดการยืม - คืนอุปกรณ์ภารกิจ
                </h5>
                <button type="button" className="btn-close" onClick={() => setShowEquipModal(false)}></button>
              </div>
              <div className="modal-body p-4">
                <h6 className="fw-bold mb-3 text-primary">1. รายการอุปกรณ์ที่ยืมอยู่ในปัจจุบัน:</h6>
                {docBorrowings.length === 0 ? (
                  <p className="text-muted small">ยังไม่มีรายการยืมอุปกรณ์สำหรับภารกิจนี้</p>
                ) : (
                  <table className="table table-bordered table-sm align-middle mb-4">
                    <thead className="table-light">
                      <tr>
                        <th>อุปกรณ์</th>
                        <th>จำนวน</th>
                        <th>ผู้ยืม</th>
                        <th>สถานะ</th>
                        <th>คืน</th>
                      </tr>
                    </thead>
                    <tbody>
                      {docBorrowings.map(b => (
                        <tr key={b.id}>
                          <td className="small fw-semibold">{b.equipmentName}</td>
                          <td className="small">{b.quantity} {b.unit}</td>
                          <td className="small text-muted">{b.borrowerName}</td>
                          <td>
                            <span className={`badge ${b.status === "borrowed" ? "bg-warning text-dark" : "bg-success"}`}>
                              {b.status === "borrowed" ? "กำลังยืม" : "คืนแล้ว"}
                            </span>
                          </td>
                          <td>
                            {b.status === "borrowed" && (
                              <button
                                type="button"
                                onClick={() => handleReturnEquip(b.id)}
                                className="btn btn-sm btn-outline-danger py-0 px-2 rounded-pill"
                                style={{ fontSize: "0.75rem" }}
                              >
                                ส่งคืน
                              </button>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}

                <hr />
                <h6 className="fw-bold mb-3 text-success">2. เบิกยืมอุปกรณ์เพิ่ม:</h6>
                <form onSubmit={handleBorrowEquip} className="row g-2 align-items-end">
                  <div className="col-md-7">
                    <label className="form-label small fw-bold text-muted">เลือกอุปกรณ์คงคลัง</label>
                    <select
                      className="form-select form-select-sm"
                      value={selectedEquipCode}
                      onChange={(e) => setSelectedEquipCode(e.target.value)}
                      required
                    >
                      <option value="" disabled>-- เลือกอุปกรณ์ --</option>
                      {equipments.map(eq => (
                        <option key={eq.code} value={eq.code} disabled={eq.availableQty <= 0}>
                          {eq.name} (คงเหลือ: {eq.availableQty} {eq.unit})
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="col-md-2">
                    <label className="form-label small fw-bold text-muted">จำนวน</label>
                    <input
                      type="number"
                      min="1"
                      className="form-control form-control-sm"
                      value={equipQty}
                      onChange={(e) => setEquipQty(parseInt(e.target.value, 10) || 1)}
                      required
                    />
                  </div>
                  <div className="col-md-3">
                    <button type="submit" className="btn btn-sm btn-success w-100 rounded-pill">
                      <i className="bi bi-box-arrow-in-down me-1"></i>บันทึกยืม
                    </button>
                  </div>
                </form>
              </div>
              <div className="modal-footer bg-light border-0">
                <button type="button" className="btn btn-secondary rounded-pill px-4" onClick={() => setShowEquipModal(false)}>
                  ปิด
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Document Detail Modal */}
      {showDetailModal && activeDoc && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered modal-lg">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <div className="modal-header border-bottom bg-light px-4 py-3">
                <h5 className="modal-title fw-bold text-dark">
                  <i className="bi bi-file-earmark-text text-primary me-2"></i>รายละเอียดงานหนังสือ
                </h5>
                <button type="button" className="btn-close" onClick={() => setShowDetailModal(false)}></button>
              </div>
              <div className="modal-body p-4">
                <h5 className="fw-bold text-primary mb-3">{activeDoc.docName}</h5>
                <div className="row g-3">
                  <div className="col-md-6">
                    <div className="small text-muted">เลขที่รับสารบรรณ:</div>
                    <div className="fw-bold">{activeDoc.docNumber}</div>
                  </div>
                  <div className="col-md-6">
                    <div className="small text-muted">เลขที่หนังสือต้นทาง:</div>
                    <div className="fw-bold">{activeDoc.sourceDocNumber || "-"}</div>
                  </div>
                  <div className="col-md-6">
                    <div className="small text-muted">หน่วยงานเจ้าภาพ:</div>
                    <div>{activeDoc.hostAgency}{activeDoc.hostAffiliation && ` (${activeDoc.hostAffiliation})`}</div>
                  </div>
                  <div className="col-md-6">
                    <div className="small text-muted">สถานที่จัดงาน:</div>
                    <div>{activeDoc.venueName}, {activeDoc.venueSubdistrict}, {activeDoc.venueDistrict}, {activeDoc.venueProvince}</div>
                  </div>
                  <div className="col-md-6">
                    <div className="small text-muted">กำหนดการปฏิบัติงาน:</div>
                    <div>{activeDoc.startDate} ถึง {activeDoc.endDate}</div>
                  </div>
                  <div className="col-md-6">
                    <div className="small text-muted">ผู้ประสานงานโครงการ:</div>
                    <div className="fw-bold text-success">{activeDoc.coordinator} ({activeDoc.coordinatorPhone})</div>
                  </div>
                  <div className="col-12">
                    <div className="small text-muted">รายละเอียดเนื้อหาภารกิจ:</div>
                    <p className="bg-light p-3 rounded-3 mt-1 small">{activeDoc.description}</p>
                  </div>
                </div>
              </div>
              <div className="modal-footer bg-light border-0">
                <button type="button" className="btn btn-secondary rounded-pill px-4" onClick={() => setShowDetailModal(false)}>
                  ปิด
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}


