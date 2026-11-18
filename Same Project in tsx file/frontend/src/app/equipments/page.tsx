"use client";

import React, { useState, useEffect, useCallback } from "react";
import { useDemo } from "@/context/DemoContext";
import { api } from "@/services/api";

export default function EquipmentsPage() {
  const { currentRole, showNotice } = useDemo();
  const [equipments, setEquipments] = useState([]);
  const [borrowings, setBorrowings] = useState([]);
  const [documents, setDocuments] = useState([]);

  // Modal State
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState({
    docId: "",
    equipmentCode: "",
    quantity: 1,
  });

  const fetchData = useCallback(() =>
    Promise.all([
      api.getEquipments(currentRole),
      api.getDocuments({}, currentRole),
    ]).then(([resEq, resDocs]) => {
      if (resEq.success) {
        setEquipments(resEq.data);
        setBorrowings(resEq.borrowings || []);
      }
      if (resDocs.success) setDocuments(resDocs.data);
    }), [currentRole]);

  useEffect(() => {
    fetchData();
    const handleReset = () => fetchData();
    window.addEventListener("demo-data-reset", handleReset);
    return () => window.removeEventListener("demo-data-reset", handleReset);
  }, [fetchData]);

  const handleBorrow = async (e) => {
    e.preventDefault();
    const res = await api.borrowEquipment(form, currentRole);
    if (res.success) {
      showNotice(res.message, "success");
      setShowModal(false);
      fetchData();
    } else {
      showNotice(res.message || res.error, "error");
    }
  };

  const handleReturn = async (id) => {
    const res = await api.returnEquipment(id, currentRole);
    if (res.success) {
      showNotice(res.message, "success");
      fetchData();
    } else {
      showNotice(res.message || res.error, "error");
    }
  };

  return (
    <div>
      <div className="page-header text-center" style={{ background: "linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)", color: "white", padding: "2rem 0", marginBottom: "2rem" }}>
        <div className="container">
          <h2 className="fw-bold mb-1">
            <i className="bi bi-box-seam me-2"></i>
            ระบบคลังอุปกรณ์และพัสดุพิธีการ
          </h2>
          <p className="mb-0 text-white-50">
            ควบคุมการเบิกยืม-ส่งคืนอุปกรณ์พิธีการ เครื่องเสียง เต็นท์พิธีการ และเครื่องกำเนิดไฟฟ้า
          </p>
        </div>
      </div>

      <div className="container">
        <div className="d-flex justify-content-between align-items-center mb-4">
          <h5 className="fw-bold text-dark m-0">
            <i className="bi bi-boxes text-danger me-2"></i>
            รายการอุปกรณ์ทั้งหมดในคลัง
          </h5>
          <button
            className="btn btn-primary-gradient px-3 py-2 rounded-3"
            onClick={() => {
              if (documents.length > 0 && equipments.length > 0) {
                setForm({ docId: documents[0].id, equipmentCode: equipments[0].code, quantity: 1 });
              }
              setShowModal(true);
            }}
          >
            <i className="bi bi-plus-circle me-1"></i> เบิกยืมอุปกรณ์เข้าพื้นที่
          </button>
        </div>

        {/* Equipment Cards */}
        <div className="row g-3 mb-4">
          {equipments.map((eq) => (
            <div key={eq.id} className="col-12 col-md-6 col-lg-4">
              <div className="card custom-card p-3 h-100">
                <div className="d-flex justify-content-between align-items-start mb-2">
                  <span className="badge bg-secondary font-monospace">{eq.code}</span>
                  <span className={`badge ${eq.availableQty <= 2 ? "bg-danger" : "bg-success"}`}>
                    เหลือ {eq.availableQty} {eq.unit}
                  </span>
                </div>
                <h6 className="fw-bold text-dark mb-1">{eq.name}</h6>
                <div className="text-muted small mb-2">หมวดหมู่: {eq.category}</div>
                <div className="border-top pt-2 small text-secondary">
                  จำนวนทั้งหมดในคลัง: <strong>{eq.totalQty} {eq.unit}</strong>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Borrowing Table */}
        <div className="card custom-card">
          <div className="card-header bg-white py-3 border-bottom">
            <h6 className="m-0 fw-bold text-dark">
              <i className="bi bi-clipboard-data text-primary me-2"></i>
              ประวัติการเบิกยืมอุปกรณ์
            </h6>
          </div>
          <div className="table-responsive">
            <table className="table table-hover table-custom align-middle mb-0">
              <thead>
                <tr>
                  <th>รหัสอุปกรณ์</th>
                  <th>ชื่ออุปกรณ์</th>
                  <th className="text-center">จำนวนที่ยืม</th>
                  <th>ผู้เบิกยืม</th>
                  <th>ช่วงเวลายืม</th>
                  <th className="text-center">สถานะ</th>
                  <th className="text-center">การกระทำ</th>
                </tr>
              </thead>
              <tbody>
                {borrowings.map((b) => (
                  <tr key={b.id}>
                    <td><span className="font-monospace fw-bold">{b.equipmentCode}</span></td>
                    <td><span className="fw-semibold text-dark">{b.equipmentName}</span></td>
                    <td className="text-center"><span className="badge bg-light text-dark border">{b.quantity} {b.unit}</span></td>
                    <td>{b.borrowerName}</td>
                    <td><small className="text-muted">{b.borrowDate} ถึง {b.returnDate}</small></td>
                    <td className="text-center">
                      {b.status === "borrowed" ? (
                        <span className="badge bg-warning text-dark">กำลังยืม</span>
                      ) : (
                        <span className="badge bg-success">ส่งคืนแล้ว</span>
                      )}
                    </td>
                    <td className="text-center">
                      {b.status === "borrowed" && (
                        <button
                          className="btn btn-sm btn-outline-success rounded-pill px-2 py-1"
                          onClick={() => handleReturn(b.id)}
                        >
                          <i className="bi bi-box-arrow-in-down"></i> ส่งคืน
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Modal: Borrow */}
      {showModal && (
        <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog">
            <div className="modal-content rounded-3 shadow">
              <div className="modal-header bg-light">
                <h5 className="modal-title fw-bold text-dark">
                  <i className="bi bi-box-seam text-primary me-2"></i>
                  เบิกยืมอุปกรณ์สำหรับภารกิจ
                </h5>
                <button type="button" className="btn-close" onClick={() => setShowModal(false)}></button>
              </div>
              <form onSubmit={handleBorrow}>
                <div className="modal-body p-4">
                  <div className="mb-3">
                    <label className="form-label">เลือกภารกิจ</label>
                    <select
                      required
                      className="form-select"
                      value={form.docId}
                      onChange={(e) => setForm({ ...form, docId: e.target.value })}
                    >
                      <option value="">-- เลือกภารกิจ --</option>
                      {documents.map((d) => (
                        <option key={d.id} value={d.id}>
                          {d.docNumber} - {d.docName}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="mb-3">
                    <label className="form-label">เลือกอุปกรณ์</label>
                    <select
                      required
                      className="form-select"
                      value={form.equipmentCode}
                      onChange={(e) => setForm({ ...form, equipmentCode: e.target.value })}
                    >
                      <option value="">-- เลือกอุปกรณ์ --</option>
                      {equipments.map((eq) => (
                        <option key={eq.id} value={eq.code}>
                          {eq.name} (คงเหลือ: {eq.availableQty} {eq.unit})
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="mb-3">
                    <label className="form-label">จำนวนที่ต้องการเบิก</label>
                    <input
                      type="number"
                      min="1"
                      required
                      className="form-control"
                      value={form.quantity}
                      onChange={(e) => setForm({ ...form, quantity: parseInt(e.target.value) || 1 })}
                    />
                  </div>
                </div>
                <div className="modal-footer bg-light">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowModal(false)}>ยกเลิก</button>
                  <button type="submit" className="btn btn-primary-gradient px-4">ยืนยันเบิกยืม</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
