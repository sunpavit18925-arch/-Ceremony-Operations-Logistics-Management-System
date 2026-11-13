"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";

export default function StaffViewOperationPage() {
  const { currentRole, currentUser } = useDemo();
  const [documents, setDocuments] = useState([]);
  const [activeDoc, setActiveDoc] = useState(null);
  const [showDetailModal, setShowDetailModal] = useState(false);
  const [loading, setLoading] = useState(true);

  const fetchDocs = async () => {
    try {
      const res = await fetch("http://localhost:4000/api/demo/documents", {
        headers: { "x-demo-role": currentRole }
      });
      const data = await res.json();
      if (data.success) {
        setDocuments(data.data);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDocs();
  }, [currentRole]);

  return (
    <div className="content-wrapper" style={{ backgroundColor: "var(--app-muted-bg)", minHeight: "100vh" }}>
      {/* Header matching PHP staff_view_operation.php */}
      <div className="page-header shadow-sm bg-white p-4 border-bottom mb-4">
        <div className="container d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
          <div>
            <h4 className="fw-bold mb-1">งานที่ได้รับมอบหมาย</h4>
            <span
              className="role-badge"
              style={{
                fontSize: "0.8rem",
                padding: "0.4rem 1rem",
                borderRadius: "50rem",
                fontWeight: 600,
                backgroundColor: "var(--bs-success-bg-subtle)",
                color: "var(--bs-success-text-emphasis)",
                display: "inline-block"
              }}
            >
              <i className="bi bi-shield-check me-1"></i>ฝ่ายปฏิบัติการ
            </span>
          </div>
          <div className="text-md-end">
            <small className="text-muted d-block">ผู้ใช้งาน:</small>
            <div className="fw-bold">{currentUser?.name || "กิตติ พร้อมลงพื้นที่"}</div>
          </div>
        </div>
      </div>

      <div className="container">
        <div className="card card-custom overflow-hidden shadow-sm border-0" style={{ borderRadius: "15px" }}>
          <div className="card-body p-0">
            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0">
                <thead className="table-light">
                  <tr>
                    <th className="ps-4" style={{ width: "40%" }}>ข้อมูลหนังสือ</th>
                    <th style={{ width: "25%" }}>ส่งมาจากหัวหน้า</th>
                    <th className="text-center" style={{ width: "20%" }}>วันที่ได้รับ</th>
                    <th className="text-center" style={{ width: "15%" }}>จัดการ</th>
                  </tr>
                </thead>
                <tbody>
                  {documents.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="text-center py-5 text-muted">
                        ยังไม่มีงานในขณะนี้
                      </td>
                    </tr>
                  ) : (
                    documents.map(doc => (
                      <tr key={doc.id}>
                        <td className="ps-4">
                          <div className="fw-bold text-dark">{doc.docName}</div>
                          <div className="small text-muted">{doc.description}</div>
                          <small className="text-primary fw-semibold">{doc.docNumber}</small>
                        </td>
                        <td>
                          <span className="badge bg-light text-dark border">
                            <i className="bi bi-person-badge me-1"></i>{doc.createdBy || "หัวหน้าฝ่ายสารบรรณ"}
                          </span>
                        </td>
                        <td className="text-center small text-muted">
                          {new Date(doc.receivedDate).toLocaleDateString("th-TH")}
                        </td>
                        <td className="text-center">
                          <button
                            type="button"
                            onClick={() => { setActiveDoc(doc); setShowDetailModal(true); }}
                            className="btn btn-sm btn-outline-primary rounded-pill px-3"
                          >
                            <i className="bi bi-eye-fill me-1"></i>ดูรายละเอียด
                          </button>
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


