"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";

export default function StaffViewOperation2Page() {
  const { currentRole, currentUser } = useDemo();
  const [documents, setDocuments] = useState([]);
  const [equipments, setEquipments] = useState([]);
  const [borrowings, setBorrowings] = useState([]);
  const [activeDoc, setActiveDoc] = useState(null);
  const [showMeetingModal, setShowMeetingModal] = useState(false);
  const [showEquipModal, setShowEquipModal] = useState(false);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [alertMsg, setAlertMsg] = useState(null);
  const [loading, setLoading] = useState(true);

  // Meeting form
  const [meetingTopic, setMeetingTopic] = useState("");
  const [meetingSummary, setMeetingSummary] = useState("");
  const [meetingDate, setMeetingDate] = useState("");
  const [meetingLocation, setMeetingLocation] = useState("");
  const [meetingChairman, setMeetingChairman] = useState("");

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
      if (equipData.success) {
        setEquipments(equipData.data);
        setBorrowings(equipData.borrowings || []);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [currentRole]);

  const handleAcknowledge = async (docId) => {
    try {
      const res = await fetch(`http://localhost:4000/api/demo/documents/${docId}/acknowledge`, {
        method: "POST",
        headers: { "x-demo-role": currentRole }
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg("ยืนยันรับทราบงานเรียบร้อยแล้ว");
        fetchData();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  const handleSaveMeeting = async (e) => {
    e.preventDefault();
    if (!activeDoc) return;

    try {
      const res = await fetch(`http://localhost:4000/api/demo/documents/${activeDoc.id}/meeting-report`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({
          topic: meetingTopic,
          summary: meetingSummary,
          meetingDate: meetingDate || new Date().toISOString().split("T")[0],
          location: meetingLocation || activeDoc.venueName,
          chairmanName: meetingChairman || "หัวหน้าฝ่ายปฏิบัติการ"
        })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg("บันทึกรายงานการประชุมเรียบร้อยแล้ว");
        setShowMeetingModal(false);
        setMeetingTopic("");
        setMeetingSummary("");
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
      {/* Header matching PHP staff_view_operation2.php */}
      <div className="bg-white border-bottom shadow-sm py-4 mb-4">
        <div className="container d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
          <div>
            <Link href="/home_operation" className="btn btn-outline-secondary btn-sm rounded-pill mb-2">
              <i className="bi bi-arrow-left me-1"></i>กลับหน้าหลัก
            </Link>
            <h3 className="fw-bold text-dark mb-1">
              <i className="bi bi-clipboard-check text-primary me-2"></i>งานที่ได้รับมอบหมาย
            </h3>
            <span className="badge bg-primary bg-opacity-10 text-primary border rounded-pill px-3 py-1">
              ผู้ประสานงานฝ่ายปฏิบัติการ
            </span>
          </div>
          <div className="text-md-end">
            <small className="text-muted d-block">ผู้ใช้งานปัจจุบัน:</small>
            <div className="fw-bold text-dark">{currentUser?.name || "ณัฐพงษ์ ประสานมิตร"}</div>
          </div>
        </div>
      </div>

      <div className="container">
        {alertMsg && (
          <div className="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center alert-dismissible">
            <i className="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
            <div><h6 className="mb-0 fw-bold">{alertMsg}</h6></div>
            <button type="button" className="btn-close ms-auto" onClick={() => setAlertMsg(null)}></button>
          </div>
        )}

        <div className="card shadow-sm border-0" style={{ borderRadius: "1rem", overflow: "hidden" }}>
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th style={{ width: "30%" }} className="ps-4">ข้อมูลหนังสือ</th>
                  <th style={{ width: "20%" }}>กำหนดการและสถานที่</th>
                  <th style={{ width: "15%" }} className="text-center">การรับทราบ</th>
                  <th style={{ width: "15%" }} className="text-center">รายงานประชุม</th>
                  <th style={{ width: "20%" }} className="text-center">จัดการ</th>
                </tr>
              </thead>
              <tbody>
                {documents.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="text-center py-5 text-muted">
                      <i className="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                      ยังไม่มีงานที่มอบหมายให้ท่านในขณะนี้
                    </td>
                  </tr>
                ) : (
                  documents.map(doc => (
                    <tr key={doc.id}>
                      <td className="ps-4">
                        <div className="fw-bold text-dark">{doc.docName}</div>
                        <small className="text-muted d-block">เลขที่: {doc.docNumber}</small>
                        <small className="text-muted">หน่วยงาน: {doc.hostAgency}</small>
                      </td>
                      <td className="small text-muted">
                        <div><i className="bi bi-calendar3 me-1"></i>{doc.startDate} ถึง {doc.endDate}</div>
                        <div><i className="bi bi-geo-alt-fill text-danger me-1"></i>{doc.venueProvince}</div>
                      </td>
                      <td className="text-center">
                        {doc.isAcknowledged ? (
                          <span className="badge bg-success rounded-pill px-3 py-2">
                            <i className="bi bi-check-circle-fill me-1"></i>รับทราบแล้ว
                          </span>
                        ) : (
                          <button
                            type="button"
                            onClick={() => handleAcknowledge(doc.id)}
                            className="btn btn-sm btn-warning text-dark fw-bold rounded-pill px-3 shadow-sm"
                          >
                            <i className="bi bi-hand-thumbs-up me-1"></i>กดยืนยันรับทราบ
                          </button>
                        )}
                      </td>
                      <td className="text-center">
                        <button
                          type="button"
                          onClick={() => { setActiveDoc(doc); setShowMeetingModal(true); }}
                          className="btn btn-sm btn-outline-primary rounded-pill px-3"
                        >
                          <i className="bi bi-pencil-square me-1"></i>บันทึกประชุม
                        </button>
                      </td>
                      <td className="text-center">
                        <div className="d-flex justify-content-center gap-1">
                          <button
                            type="button"
                            onClick={() => { setActiveDoc(doc); setShowEquipModal(true); }}
                            className="btn btn-sm btn-outline-success rounded-pill px-2"
                            title="ยืมอุปกรณ์"
                          >
                            <i className="bi bi-box-seam me-1"></i>อุปกรณ์
                          </button>
                          <button
                            type="button"
                            onClick={() => { setActiveDoc(doc); setShowDetailModal(true); }}
                            className="btn btn-sm btn-outline-info rounded-pill px-2"
                            title="ดูรายละเอียด"
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

      {/* Meeting Report Modal */}
      {showMeetingModal && activeDoc && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered modal-lg">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <form onSubmit={handleSaveMeeting}>
                <div className="modal-header border-bottom bg-light px-4 py-3">
                  <h5 className="modal-title fw-bold text-dark">
                    <i className="bi bi-journal-text text-primary me-2"></i>บันทึกรายงานการประชุมและผังงาน
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setShowMeetingModal(false)}></button>
                </div>
                <div className="modal-body p-4">
                  <p className="small text-muted mb-3">
                    ภารกิจ: <strong>{activeDoc.docName}</strong> ({activeDoc.docNumber})
                  </p>
                  <div className="row g-3">
                    <div className="col-md-6">
                      <label className="form-label small fw-bold text-secondary">วันที่ประชุม:</label>
                      <input
                        type="date"
                        className="form-control"
                        value={meetingDate}
                        onChange={(e) => setMeetingDate(e.target.value)}
                        required
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-bold text-secondary">สถานที่ประชุม:</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="เช่น ห้องประชุมสารบรรณ 1"
                        value={meetingLocation}
                        onChange={(e) => setMeetingLocation(e.target.value)}
                        required
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-bold text-secondary">ประธานในการประชุม:</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="ชื่อประธาน..."
                        value={meetingChairman}
                        onChange={(e) => setMeetingChairman(e.target.value)}
                        required
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-bold text-secondary">หัวข้อการประชุม:</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="เช่น การเตรียมความพร้อมระบบพลับพลา..."
                        value={meetingTopic}
                        onChange={(e) => setMeetingTopic(e.target.value)}
                        required
                      />
                    </div>
                    <div className="col-12">
                      <label className="form-label small fw-bold text-secondary">สรุปมติที่ประชุมและข้อสั่งการ:</label>
                      <textarea
                        className="form-control"
                        rows={4}
                        placeholder="ระบุข้อสรุป มติ และแผนการลงพื้นที่..."
                        value={meetingSummary}
                        onChange={(e) => setMeetingSummary(e.target.value)}
                        required
                      ></textarea>
                    </div>
                  </div>
                </div>
                <div className="modal-footer bg-light border-0">
                  <button type="button" className="btn btn-secondary rounded-pill px-3" onClick={() => setShowMeetingModal(false)}>
                    ยกเลิก
                  </button>
                  <button type="submit" className="btn btn-primary rounded-pill px-4">
                    <i className="bi bi-save me-1"></i>บันทึกรายงาน
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Equipment Modal */}
      {showEquipModal && activeDoc && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered modal-lg">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <div className="modal-header border-bottom bg-light px-4 py-3">
                <h5 className="modal-title fw-bold text-dark">
                  <i className="bi bi-box-seam text-info me-2"></i>รายการอุปกรณ์สำหรับภารกิจ
                </h5>
                <button type="button" className="btn-close" onClick={() => setShowEquipModal(false)}></button>
              </div>
              <div className="modal-body p-4">
                <p className="small text-muted mb-3">
                  ภารกิจ: <strong>{activeDoc.docName}</strong>
                </p>
                {docBorrowings.length === 0 ? (
                  <div className="text-center py-4 text-muted">ยังไม่มีการเบิกยืมอุปกรณ์สำหรับภารกิจนี้</div>
                ) : (
                  <table className="table table-bordered table-sm align-middle">
                    <thead className="table-light">
                      <tr>
                        <th>อุปกรณ์</th>
                        <th>จำนวน</th>
                        <th>ผู้ยืม</th>
                        <th>สถานะ</th>
                      </tr>
                    </thead>
                    <tbody>
                      {docBorrowings.map(b => (
                        <tr key={b.id}>
                          <td>{b.equipmentName}</td>
                          <td>{b.quantity} {b.unit}</td>
                          <td>{b.borrowerName}</td>
                          <td>
                            <span className="badge bg-success">เบิกยืมสำเร็จ</span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
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
                    <div>{activeDoc.hostAgency} ({activeDoc.hostAffiliation})</div>
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
                    <div className="small text-muted">รายละเอียด:</div>
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


