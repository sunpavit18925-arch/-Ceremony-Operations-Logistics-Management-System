"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";

export default function SelectMissionPage() {
  const { currentRole, currentUser } = useDemo();
  const [documents, setDocuments] = useState([]);
  const [equipments, setEquipments] = useState([]);
  const [searchQuery, setSearchQuery] = useState("");
  const [activeDoc, setActiveDoc] = useState(null);
  const [showCatalogModal, setShowCatalogModal] = useState(false);
  const [showBorrowModal, setShowBorrowModal] = useState(false);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [selectedEquipCode, setSelectedEquipCode] = useState("");
  const [borrowQty, setBorrowQty] = useState(1);
  const [alertMsg, setAlertMsg] = useState(null);
  const [loading, setLoading] = useState(true);

  const isHead = currentRole === "head_vehicle" || currentRole === "super_admin";

  const fetchData = async () => {
    try {
      const headers = { "x-demo-role": currentRole };
      const [docRes, equipRes] = await Promise.all([
        fetch("http://localhost:4000/api/demo/documents", { headers }),
        fetch("http://localhost:4000/api/demo/equipments", { headers })
      ]);

      const docData = await docRes.json();
      const equipData = await equipRes.json();

      if (docData.success) setDocuments(docData.data);
      if (equipData.success) setEquipments(equipData.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [currentRole]);

  const filteredDocs = documents.filter(doc => {
    if (!searchQuery) return true;
    const q = searchQuery.toLowerCase();
    return (
      doc.docName.toLowerCase().includes(q) ||
      doc.docNumber.toLowerCase().includes(q)
    );
  });

  const handleBorrowEquipment = async (e) => {
    e.preventDefault();
    if (!activeDoc || !selectedEquipCode || borrowQty < 1) return;

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
          quantity: Number(borrowQty)
        })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg(data.message);
        setShowBorrowModal(false);
        setSelectedEquipCode("");
        setBorrowQty(1);
        fetchData();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  return (
    <div className="content-wrapper" style={{ background: "var(--app-page-gradient)", minHeight: "100vh" }}>
      <div className="container py-5">
        {/* Header matching PHP select_mission.php */}
        <div className="row align-items-center mb-4 pb-3 border-bottom">
          <div className="col-12 col-md-3 text-start mb-3 mb-md-0">
            <Link
              href={isHead ? "/home_head_vehicle" : "/home_vehicle"}
              className="btn btn-white border shadow-sm rounded-pill d-inline-flex align-items-center px-4 py-2 bg-white"
              style={{ fontSize: "0.95rem", fontWeight: 500 }}
            >
              <i className="bi bi-arrow-left me-2"></i>ย้อนกลับ
            </Link>
          </div>
          <div className="col-12 col-md-6 text-center">
            <h2 className="fw-bold page-header mb-0 pb-2 text-primary" style={{ position: "relative", display: "inline-block" }}>
              <i className="bi bi-car-front me-2" style={{ color: "#10b981" }}></i>การบริหารจัดการรถ
            </h2>
            <p className="text-muted mt-2 mb-0">ระบบจัดการและจัดสรรทรัพยากรสำหรับงานพิธี</p>
          </div>
          <div className="col-12 col-md-3"></div>
        </div>

        {alertMsg && (
          <div className="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center alert-dismissible">
            <i className="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
            <div><h6 className="mb-0 fw-bold">{alertMsg}</h6></div>
            <button type="button" className="btn-close ms-auto" onClick={() => setAlertMsg(null)}></button>
          </div>
        )}

        {/* Toolbar */}
        <div className="d-flex justify-content-center justify-content-md-end align-items-center gap-3 mb-4">
          <button
            type="button"
            onClick={() => setShowCatalogModal(true)}
            className="btn btn-outline-info fw-bold shadow-sm bg-white"
          >
            <i className="bi bi-tools me-1"></i> รายการอุปกรณ์
          </button>
          <div className="position-relative w-100" style={{ maxWidth: "350px" }}>
            <span className="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
              <i className="bi bi-search"></i>
            </span>
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="form-control bg-white border-0 shadow-sm ps-5 py-2"
              style={{ borderRadius: "20px" }}
              placeholder="ค้นหาชื่องาน, เลขที่หนังสือ..."
            />
          </div>
        </div>

        {/* Table Container */}
        <div
          className="table-container"
          style={{
            backgroundColor: "var(--app-surface)",
            borderRadius: "12px",
            boxShadow: "0 4px 15px rgba(0,0,0,0.04)",
            borderTop: "5px solid #3b82f6",
            padding: "15px",
            marginBottom: "2rem"
          }}
        >
          <div className="table-responsive">
            <table className="table table-bordered table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th scope="col" style={{ width: "40%" }}>ชื่องาน / เลขที่หนังสือ</th>
                  <th scope="col" className="text-center" style={{ width: "20%" }}>ชุดปฏิบัติงาน</th>
                  <th scope="col" className="text-center" style={{ width: "20%" }}>ยานพาหนะ</th>
                  {isHead && (
                    <th scope="col" className="text-center" style={{ width: "20%" }}>จัดการ</th>
                  )}
                </tr>
              </thead>
              <tbody>
                {filteredDocs.length === 0 ? (
                  <tr>
                    <td colSpan={isHead ? 4 : 3} className="text-center py-5">
                      <i className="bi bi-search text-muted fs-1 opacity-50 mb-3 d-block"></i>
                      <h5 className="fw-bold text-muted">ไม่พบข้อมูลภารกิจที่ค้นหา</h5>
                    </td>
                  </tr>
                ) : (
                  filteredDocs.map(doc => (
                    <tr key={doc.id}>
                      <td>
                        <div className="fw-bold text-dark">{doc.docName}</div>
                        <small className="text-muted d-block mt-1">เลขที่: {doc.docNumber}</small>
                        <button
                          type="button"
                          onClick={() => { setActiveDoc(doc); setShowDetailModal(true); }}
                          className="btn btn-sm btn-outline-primary rounded-pill d-inline-flex align-items-center shadow-sm mt-2"
                          style={{ fontSize: "0.75rem", padding: "0.2rem 0.75rem" }}
                        >
                          <i className="bi bi-eye-fill me-1"></i> ดูรายละเอียด
                        </button>
                      </td>
                      <td className="text-center">
                        {doc.teamSet || doc.assignedToId ? (
                          <div
                            className="status-badge"
                            style={{
                              display: "inline-flex",
                              alignItems: "center",
                              gap: "6px",
                              padding: "6px 14px",
                              borderRadius: "50rem",
                              fontSize: "0.85rem",
                              fontWeight: 500,
                              backgroundColor: "var(--bs-success-bg-subtle)",
                              color: "var(--bs-success-text-emphasis)"
                            }}
                          >
                            <i className="bi bi-check-circle-fill"></i> จัดชุดแล้ว
                          </div>
                        ) : (
                          <div
                            className="status-badge"
                            style={{
                              display: "inline-flex",
                              alignItems: "center",
                              gap: "6px",
                              padding: "6px 14px",
                              borderRadius: "50rem",
                              fontSize: "0.85rem",
                              fontWeight: 500,
                              backgroundColor: "var(--bs-danger-bg-subtle)",
                              color: "var(--bs-danger-text-emphasis)"
                            }}
                          >
                            <i className="bi bi-x-circle-fill"></i> รอผู้ปฏิบัติการ
                          </div>
                        )}
                      </td>
                      <td className="text-center">
                        {doc.vehicleAssigned ? (
                          <div
                            className="status-badge"
                            style={{
                              display: "inline-flex",
                              alignItems: "center",
                              gap: "6px",
                              padding: "6px 14px",
                              borderRadius: "50rem",
                              fontSize: "0.85rem",
                              fontWeight: 500,
                              backgroundColor: "var(--bs-success-bg-subtle)",
                              color: "var(--bs-success-text-emphasis)"
                            }}
                          >
                            <i className="bi bi-check-circle-fill"></i> จัดเรียบร้อย
                          </div>
                        ) : (
                          <div
                            className="status-badge"
                            style={{
                              display: "inline-flex",
                              alignItems: "center",
                              gap: "6px",
                              padding: "6px 14px",
                              borderRadius: "50rem",
                              fontSize: "0.85rem",
                              fontWeight: 500,
                              backgroundColor: "var(--bs-danger-bg-subtle)",
                              color: "var(--bs-danger-text-emphasis)"
                            }}
                          >
                            <i className="bi bi-x-circle-fill"></i> ยังไม่ได้จัด
                          </div>
                        )}
                      </td>
                      {isHead && (
                        <td className="text-center">
                          <div className="d-flex justify-content-center gap-2">
                            <Link
                              href={`/manage_vehicle?job_id=${doc.id}`}
                              className="btn btn-sm btn-primary rounded-pill px-3 py-2 d-inline-flex align-items-center text-white"
                              style={{
                                background: "linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)",
                                border: "none"
                              }}
                            >
                              <i className="bi bi-truck me-1"></i> จัดรถ
                            </Link>
                            <button
                              type="button"
                              onClick={() => { setActiveDoc(doc); setShowBorrowModal(true); }}
                              className="btn btn-outline-success px-3 py-2 rounded-pill btn-sm d-inline-flex align-items-center"
                            >
                              <i className="bi bi-box-seam me-1"></i> ยืมอุปกรณ์
                            </button>
                          </div>
                        </td>
                      )}
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Equipment Catalog Modal */}
      {showCatalogModal && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered modal-lg">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <div className="modal-header border-bottom bg-light px-4 py-3">
                <h5 className="modal-title fw-bold text-dark">
                  <i className="bi bi-tools text-info me-2"></i>รายการอุปกรณ์และพัสดุในคลังกลาง
                </h5>
                <button type="button" className="btn-close" onClick={() => setShowCatalogModal(false)}></button>
              </div>
              <div className="modal-body p-4">
                <table className="table table-hover align-middle">
                  <thead className="table-light">
                    <tr>
                      <th>รหัส</th>
                      <th>ชื่ออุปกรณ์</th>
                      <th>หมวดหมู่</th>
                      <th>จำนวนทั้งหมด</th>
                      <th>คงเหลือ</th>
                    </tr>
                  </thead>
                  <tbody>
                    {equipments.map(eq => (
                      <tr key={eq.id}>
                        <td><code>{eq.code}</code></td>
                        <td className="fw-semibold">{eq.name}</td>
                        <td><span className="badge bg-light text-dark border">{eq.category}</span></td>
                        <td>{eq.totalQty} {eq.unit}</td>
                        <td>
                          <span className={`badge ${eq.availableQty > 0 ? "bg-success" : "bg-danger"}`}>
                            {eq.availableQty} {eq.unit}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="modal-footer bg-light border-0">
                <button type="button" className="btn btn-secondary rounded-pill px-4" onClick={() => setShowCatalogModal(false)}>
                  ปิด
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Borrow Equipment Modal */}
      {showBorrowModal && activeDoc && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <form onSubmit={handleBorrowEquipment}>
                <div className="modal-header border-bottom bg-light px-4 py-3">
                  <h5 className="modal-title fw-bold text-dark">
                    <i className="bi bi-box-seam text-success me-2"></i>ยืมอุปกรณ์สำหรับภารกิจ
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setShowBorrowModal(false)}></button>
                </div>
                <div className="modal-body p-4">
                  <p className="small text-muted mb-3">
                    ภารกิจ: <strong>{activeDoc.docName}</strong>
                  </p>
                  <label className="form-label small fw-bold text-secondary">เลือกอุปกรณ์:</label>
                  <select
                    className="form-select mb-3"
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

                  <label className="form-label small fw-bold text-secondary">จำนวนที่ต้องการยืม:</label>
                  <input
                    type="number"
                    min="1"
                    className="form-control"
                    value={borrowQty}
                    onChange={(e) => setBorrowQty(parseInt(e.target.value, 10) || 1)}
                    required
                  />
                </div>
                <div className="modal-footer bg-light border-0">
                  <button type="button" className="btn btn-secondary rounded-pill px-3" onClick={() => setShowBorrowModal(false)}>
                    ยกเลิก
                  </button>
                  <button type="submit" className="btn btn-success rounded-pill px-4">
                    ยืนยันการยืม
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Doc Detail Modal */}
      {showDetailModal && activeDoc && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered modal-lg">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <div className="modal-header border-bottom bg-light px-4 py-3">
                <h5 className="modal-title fw-bold text-dark">
                  <i className="bi bi-file-earmark-text text-primary me-2"></i>รายละเอียดงาน
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
                    <div className="small text-muted">หน่วยงานเจ้าภาพ:</div>
                    <div>{activeDoc.hostAgency}</div>
                  </div>
                  <div className="col-md-6">
                    <div className="small text-muted">สถานที่จัดงาน:</div>
                    <div>{activeDoc.venueName}, {activeDoc.venueProvince}</div>
                  </div>
                  <div className="col-md-6">
                    <div className="small text-muted">กำหนดการ:</div>
                    <div>{activeDoc.startDate} ถึง {activeDoc.endDate}</div>
                  </div>
                  <div className="col-12">
                    <div className="small text-muted">รายละเอียดภารกิจ:</div>
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


