"use client";

import React, { useState, useEffect, useCallback } from "react";
import { useDemo } from "@/context/DemoContext";
import { api } from "@/services/api";

export default function DocumentsPage() {
  const { currentRole, showNotice } = useDemo();
  const [documents, setDocuments] = useState([]);
  const [search, setSearch] = useState("");

  // Modal State
  const [showModal, setShowModal] = useState(false);
  const [showAssignModal, setShowAssignModal] = useState(false);
  const [selectedDoc, setSelectedDoc] = useState(null);

  // Form State
  const [formData, setFormData] = useState({
    docNumber: "",
    sourceDocNumber: "",
    docName: "",
    jobType: "พิธีการ",
    hostAgency: "",
    venueName: "",
    venueProvince: "กรุงเทพมหานคร",
    startDate: "",
    endDate: "",
  });

  const [assignForm, setAssignForm] = useState({
    coordinatorName: "ณัฐพงษ์ ประสานมิตร",
    coordinatorPhone: "081-444-5555",
  });

  const fetchDocs = useCallback(() =>
    api.getDocuments({}, currentRole).then((res) => {
      if (res.success) setDocuments(res.data);
    }), [currentRole]);

  useEffect(() => {
    fetchDocs();
    const handleReset = () => fetchDocs();
    window.addEventListener("demo-data-reset", handleReset);
    return () => window.removeEventListener("demo-data-reset", handleReset);
  }, [fetchDocs]);

  const handleCreateSubmit = async (e) => {
    e.preventDefault();
    const res = await api.createDocument(formData, currentRole);
    if (res.success) {
      showNotice("ลงทะเบียนรับหนังสือสำเร็จ", "success");
      setShowModal(false);
      setFormData({
        docNumber: "",
        sourceDocNumber: "",
        docName: "",
        jobType: "พิธีการ",
        hostAgency: "",
        venueName: "",
        venueProvince: "กรุงเทพมหานคร",
        startDate: "",
        endDate: "",
      });
      fetchDocs();
    } else {
      showNotice(res.message || res.error, "error");
    }
  };

  const handleAssignSubmit = async (e) => {
    e.preventDefault();
    if (!selectedDoc) return;
    const res = await api.assignCoordinator(selectedDoc.id, assignForm, currentRole);
    if (res.success) {
      showNotice("มอบหมายงานผู้ประสานงานสำเร็จ", "success");
      setShowAssignModal(false);
      fetchDocs();
    } else {
      showNotice(res.message || res.error, "error");
    }
  };

  const handleAcknowledge = async (id) => {
    const res = await api.acknowledgeDocument(id, currentRole);
    if (res.success) {
      showNotice("ยืนยันรับทราบงานสำเร็จ", "success");
      fetchDocs();
    } else {
      showNotice(res.message || res.error, "error");
    }
  };

  const filteredDocs = documents.filter((d) => {
    const q = search.toLowerCase();
    return (
      d.docNumber.toLowerCase().includes(q) ||
      d.docName.toLowerCase().includes(q) ||
      d.hostAgency.toLowerCase().includes(q)
    );
  });

  const isHeadOp = currentRole === "head_operation" || currentRole === "super_admin";
  const isCoord = currentRole === "coordinator_operation" || currentRole === "super_admin";

  return (
    <div>
      {/* Page Header identical to PHP admin2.php */}
      <div className="page-header text-center" style={{ background: "linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)", color: "white", padding: "2rem 0", marginBottom: "2rem" }}>
        <div className="container">
          <h2 className="fw-bold mb-1">
            <i className="bi bi-file-earmark-arrow-down me-2"></i>
            ระบบรับข้อมูลงานและหนังสือราชการ
          </h2>
          <p className="mb-0 text-white-50">
            ลงทะเบียนรับหนังสือ มอบหมายงาน และติดตามสถานะความคืบหน้าของภารกิจ
          </p>
        </div>
      </div>

      <div className="container">
        {/* Actions Bar */}
        <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
          <div className="d-flex align-items-center gap-2">
            {(currentRole === "admin" || currentRole === "super_admin") && (
              <button
                className="btn btn-primary-gradient px-3 py-2 rounded-3 shadow-sm"
                onClick={() => setShowModal(true)}
              >
                <i className="bi bi-plus-circle me-1"></i> ลงทะเบียนรับหนังสือใหม่
              </button>
            )}
            <span className="text-muted small">ทั้งหมด {filteredDocs.length} รายการ</span>
          </div>
          <div>
            <input
              type="text"
              className="form-control form-control-sm"
              placeholder="ค้นหาเลขที่หนังสือ หรือชื่อโครงการ..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              style={{ width: "260px" }}
            />
          </div>
        </div>

        {/* Custom Card Table identical to PHP admin2.php */}
        <div className="card custom-card">
          <div className="table-responsive">
            <table className="table table-hover table-custom align-middle mb-0">
              <thead>
                <tr>
                  <th style={{ width: "120px" }}>เลขที่รับ</th>
                  <th style={{ minWidth: "250px" }}>ชื่อโครงการ / ภารกิจ</th>
                  <th>หน่วยงานเจ้าภาพ</th>
                  <th>สถานที่ / วันที่</th>
                  <th className="text-center">ผู้ประสานงาน</th>
                  <th className="text-center">สถานะ</th>
                  <th className="text-center" style={{ width: "140px" }}>ดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
                {filteredDocs.map((doc) => (
                  <tr key={doc.id}>
                    <td>
                      <span className="fw-bold text-primary">{doc.docNumber}</span>
                      <br />
                      <small className="text-muted">{doc.sourceDocNumber}</small>
                    </td>
                    <td>
                      <div className="fw-semibold text-dark">{doc.docName}</div>
                      <span className="badge bg-info text-dark bg-opacity-25 border border-info rounded-pill" style={{ fontSize: "0.72rem" }}>
                        {doc.jobType}
                      </span>
                    </td>
                    <td>
                      <span className="text-secondary">{doc.hostAgency}</span>
                    </td>
                    <td>
                      <div className="text-dark small"><i className="bi bi-geo-alt text-danger me-1"></i>{doc.venueName} ({doc.venueProvince})</div>
                      <small className="text-muted"><i className="bi bi-calendar3 me-1"></i>{doc.startDate} ถึง {doc.endDate}</small>
                    </td>
                    <td className="text-center">
                      {doc.coordinator && doc.coordinator !== "ยังไม่กำหนด" ? (
                        <div>
                          <span className="badge bg-light text-dark border">
                            <i className="bi bi-person-check text-success me-1"></i>
                            {doc.coordinator}
                          </span>
                        </div>
                      ) : (
                        <span className="text-muted small">- ยังไม่กำหนด -</span>
                      )}
                    </td>
                    <td className="text-center">
                      {doc.status === "pending" && <span className="badge bg-warning text-dark">รอมอบหมาย</span>}
                      {doc.status === "assigned" && <span className="badge bg-info text-dark">มอบหมายแล้ว</span>}
                      {doc.status === "in_progress" && <span className="badge bg-primary">กำลังดำเนินการ</span>}
                      {doc.status === "completed" && <span className="badge bg-success">เสร็จสิ้น</span>}
                    </td>
                    <td className="text-center">
                      {isHeadOp && doc.status === "pending" && (
                        <button
                          className="btn btn-sm btn-outline-primary rounded-pill px-2 py-1"
                          onClick={() => {
                            setSelectedDoc(doc);
                            setShowAssignModal(true);
                          }}
                        >
                          <i className="bi bi-person-plus"></i> มอบหมาย
                        </button>
                      )}
                      {isCoord && doc.status === "assigned" && (
                        <button
                          className="btn btn-sm btn-success rounded-pill px-2 py-1"
                          onClick={() => handleAcknowledge(doc.id)}
                        >
                          <i className="bi bi-check-circle"></i> รับทราบ
                        </button>
                      )}
                      {doc.status === "in_progress" && (
                        <span className="text-success small fw-semibold">
                          <i className="bi bi-check2-all"></i> กำลังปฏิบัติการ
                        </span>
                      )}
                      {doc.status === "completed" && (
                        <span className="text-muted small">เรียบร้อย</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Modal: New Document matching PHP admin2.php */}
      {showModal && (
        <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-lg">
            <div className="modal-content rounded-3 shadow">
              <div className="modal-header bg-light">
                <h5 className="modal-title fw-bold text-dark">
                  <i className="bi bi-file-earmark-plus text-primary me-2"></i>
                  ลงทะเบียนรับหนังสือราชการใหม่
                </h5>
                <button type="button" className="btn-close" onClick={() => setShowModal(false)}></button>
              </div>
              <form onSubmit={handleCreateSubmit}>
                <div className="modal-body p-4">
                  <div className="row g-3">
                    <div className="col-md-6">
                      <label className="form-label">เลขที่หนังสือรับ</label>
                      <input
                        type="text"
                        required
                        className="form-control"
                        placeholder="เช่น มท 0701/105"
                        value={formData.docNumber}
                        onChange={(e) => setFormData({ ...formData, docNumber: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label">เลขที่หนังสือต้นทาง</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="เช่น ว 220/2567"
                        value={formData.sourceDocNumber}
                        onChange={(e) => setFormData({ ...formData, sourceDocNumber: e.target.value })}
                      />
                    </div>
                    <div className="col-12">
                      <label className="form-label">ชื่อเรื่อง / โครงการ</label>
                      <input
                        type="text"
                        required
                        className="form-control"
                        placeholder="ระบุชื่อโครงการหรือภารกิจพิธีการ"
                        value={formData.docName}
                        onChange={(e) => setFormData({ ...formData, docName: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label">ประเภทงาน</label>
                      <select
                        className="form-select"
                        value={formData.jobType}
                        onChange={(e) => setFormData({ ...formData, jobType: e.target.value })}
                      >
                        <option value="พิธีการ">พิธีการ</option>
                        <option value="วางศิลาฤกษ์">วางศิลาฤกษ์</option>
                        <option value="เปิดอาคาร">เปิดอาคาร</option>
                        <option value="ประชุมรับฟังความคิดเห็น">ประชุมรับฟังความคิดเห็น</option>
                      </select>
                    </div>
                    <div className="col-md-6">
                      <label className="form-label">หน่วยงานเจ้าภาพ</label>
                      <input
                        type="text"
                        required
                        className="form-control"
                        placeholder="เช่น สำนักผังเมืองรวม"
                        value={formData.hostAgency}
                        onChange={(e) => setFormData({ ...formData, hostAgency: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label">สถานที่จัดงาน</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="เช่น ศาลากลางจังหวัด"
                        value={formData.venueName}
                        onChange={(e) => setFormData({ ...formData, venueName: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label">จังหวัด</label>
                      <input
                        type="text"
                        className="form-control"
                        value={formData.venueProvince}
                        onChange={(e) => setFormData({ ...formData, venueProvince: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label">วันที่เริ่ม</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.startDate}
                        onChange={(e) => setFormData({ ...formData, startDate: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label">วันที่สิ้นสุด</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.endDate}
                        onChange={(e) => setFormData({ ...formData, endDate: e.target.value })}
                      />
                    </div>
                  </div>
                </div>
                <div className="modal-footer bg-light">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowModal(false)}>ยกเลิก</button>
                  <button type="submit" className="btn btn-primary-gradient px-4">บันทึกรับหนังสือ</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Modal: Assign Coordinator */}
      {showAssignModal && selectedDoc && (
        <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog">
            <div className="modal-content rounded-3 shadow">
              <div className="modal-header bg-light">
                <h5 className="modal-title fw-bold text-dark">
                  <i className="bi bi-person-plus text-primary me-2"></i>
                  มอบหมายผู้ประสานงานภารกิจ
                </h5>
                <button type="button" className="btn-close" onClick={() => setShowAssignModal(false)}></button>
              </div>
              <form onSubmit={handleAssignSubmit}>
                <div className="modal-body p-4">
                  <p className="text-muted small mb-3">
                    โครงการ: <strong>{selectedDoc.docName}</strong> ({selectedDoc.docNumber})
                  </p>
                  <div className="mb-3">
                    <label className="form-label">ชื่อผู้ประสานงาน</label>
                    <input
                      type="text"
                      required
                      className="form-control"
                      value={assignForm.coordinatorName}
                      onChange={(e) => setAssignForm({ ...assignForm, coordinatorName: e.target.value })}
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">เบอร์ติดต่อ</label>
                    <input
                      type="text"
                      required
                      className="form-control"
                      value={assignForm.coordinatorPhone}
                      onChange={(e) => setAssignForm({ ...assignForm, coordinatorPhone: e.target.value })}
                    />
                  </div>
                </div>
                <div className="modal-footer bg-light">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowAssignModal(false)}>ยกเลิก</button>
                  <button type="submit" className="btn btn-success px-4">ยืนยันมอบหมาย</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
