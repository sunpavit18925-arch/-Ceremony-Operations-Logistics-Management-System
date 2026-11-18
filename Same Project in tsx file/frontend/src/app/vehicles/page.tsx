"use client";

import React, { useState, useEffect, useCallback } from "react";
import { useDemo } from "@/context/DemoContext";
import { api } from "@/services/api";

export default function VehiclesPage() {
  const { currentRole, showNotice } = useDemo();
  const [vehicles, setVehicles] = useState([]);
  const [assignments, setAssignments] = useState([]);
  const [pendingDocs, setPendingDocs] = useState([]);

  // Modal State
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState({
    docId: "",
    vehicleId: "",
    driverName: "วิชัย ขับปลอดภัย",
    assistant1: "กิตติ พร้อมลงพื้นที่",
  });

  const fetchData = useCallback(() =>
    Promise.all([
      api.getVehicles(currentRole),
      api.getDocuments({}, currentRole),
    ]).then(([resVeh, resDocs]) => {
      if (resVeh.success) {
        setVehicles(resVeh.data);
        setAssignments(resVeh.assignments || []);
      }
      if (resDocs.success) {
        setPendingDocs(resDocs.data.filter((d) => !d.vehicleAssigned));
      }
    }), [currentRole]);

  useEffect(() => {
    fetchData();
    const handleReset = () => fetchData();
    window.addEventListener("demo-data-reset", handleReset);
    return () => window.removeEventListener("demo-data-reset", handleReset);
  }, [fetchData]);

  const handleAssign = async (e) => {
    e.preventDefault();
    const res = await api.assignVehicle(form, currentRole);
    if (res.success) {
      showNotice("จัดสรรรถราชการสำเร็จ", "success");
      setShowModal(false);
      fetchData();
    } else {
      showNotice(res.message || res.error, "error");
    }
  };

  const canManage = currentRole === "head_vehicle" || currentRole === "super_admin";

  return (
    <div>
      <div className="page-header text-center" style={{ background: "linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)", color: "white", padding: "2rem 0", marginBottom: "2rem" }}>
        <div className="container">
          <h2 className="fw-bold mb-1">
            <i className="bi bi-truck me-2"></i>
            ระบบจัดสรรยานพาหนะและพนักงานขับรถ
          </h2>
          <p className="mb-0 text-white-50">
            บริหารจัดการยานพาหนะราชการ พนักงานขับรถ และการจับคู่ตามภารกิจ
          </p>
        </div>
      </div>

      <div className="container">
        <div className="d-flex justify-content-between align-items-center mb-4">
          <h5 className="fw-bold text-dark m-0">
            <i className="bi bi-car-front-fill text-primary me-2"></i>
            ยานพาหนะประจำการทั้งหมด ({vehicles.length} คัน)
          </h5>
          {canManage && (
            <button
              className="btn btn-primary-gradient px-3 py-2 rounded-3"
              onClick={() => {
                if (pendingDocs.length > 0) {
                  setForm({ ...form, docId: pendingDocs[0].id, vehicleId: vehicles[0]?.id || "" });
                }
                setShowModal(true);
              }}
            >
              <i className="bi bi-plus-circle me-1"></i> จัดสรรรถตามภารกิจ
            </button>
          )}
        </div>

        {/* Vehicles Grid Cards */}
        <div className="row g-3 mb-4">
          {vehicles.map((v) => (
            <div key={v.id} className="col-12 col-md-6 col-lg-3">
              <div className="card custom-card p-3 h-100">
                <div className="d-flex justify-content-between align-items-start mb-2">
                  <span className="fw-bold fs-6 text-dark font-monospace">{v.licensePlate}</span>
                  <span className="badge bg-success bg-opacity-75">{v.status}</span>
                </div>
                <div className="text-muted small mb-2">{v.vehicleType}</div>
                <div className="border-top pt-2 small text-secondary">
                  <i className="bi bi-person-badge me-1"></i> คนขับประจำ: <strong>{v.currentDriver}</strong>
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Assignment Table */}
        <div className="card custom-card">
          <div className="card-header bg-white py-3 border-bottom">
            <h6 className="m-0 fw-bold text-dark">
              <i className="bi bi-calendar-check text-success me-2"></i>
              รายการจัดสรรยานพาหนะตามภารกิจ
            </h6>
          </div>
          <div className="table-responsive">
            <table className="table table-hover table-custom align-middle mb-0">
              <thead>
                <tr>
                  <th>เลขที่หนังสือ</th>
                  <th>ชื่อภารกิจ</th>
                  <th>ทะเบียนรถ</th>
                  <th>ประเภทรถ</th>
                  <th>พนักงานขับรถ</th>
                  <th>วันที่ปฏิบัติงาน</th>
                </tr>
              </thead>
              <tbody>
                {assignments.map((a) => (
                  <tr key={a.id}>
                    <td><span className="fw-bold text-primary">{a.docNumber}</span></td>
                    <td><div className="fw-semibold text-dark">{a.docName}</div></td>
                    <td><span className="font-monospace fw-bold">{a.licensePlate}</span></td>
                    <td><span className="badge bg-light text-dark border">{a.vehicleType}</span></td>
                    <td><i className="bi bi-person text-secondary me-1"></i>{a.driverName}</td>
                    <td><small className="text-muted">{a.startDate} ถึง {a.endDate}</small></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Modal: Assign Vehicle */}
      {showModal && (
        <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog">
            <div className="modal-content rounded-3 shadow">
              <div className="modal-header bg-light">
                <h5 className="modal-title fw-bold text-dark">
                  <i className="bi bi-truck text-primary me-2"></i>
                  จัดสรรยานพาหนะและพลขับ
                </h5>
                <button type="button" className="btn-close" onClick={() => setShowModal(false)}></button>
              </div>
              <form onSubmit={handleAssign}>
                <div className="modal-body p-4">
                  <div className="mb-3">
                    <label className="form-label">เลือกหนังสือภารกิจ</label>
                    <select
                      required
                      className="form-select"
                      value={form.docId}
                      onChange={(e) => setForm({ ...form, docId: e.target.value })}
                    >
                      <option value="">-- เลือกภารกิจ --</option>
                      {pendingDocs.map((d) => (
                        <option key={d.id} value={d.id}>
                          {d.docNumber} - {d.docName}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="mb-3">
                    <label className="form-label">เลือกยานพาหนะ</label>
                    <select
                      required
                      className="form-select"
                      value={form.vehicleId}
                      onChange={(e) => setForm({ ...form, vehicleId: e.target.value })}
                    >
                      <option value="">-- เลือกยานพาหนะ --</option>
                      {vehicles.map((v) => (
                        <option key={v.id} value={v.id}>
                          {v.licensePlate} ({v.vehicleType})
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="mb-3">
                    <label className="form-label">พนักงานขับรถยนต์</label>
                    <input
                      type="text"
                      required
                      className="form-control"
                      value={form.driverName}
                      onChange={(e) => setForm({ ...form, driverName: e.target.value })}
                    />
                  </div>

                  <div className="mb-3">
                    <label className="form-label">เจ้าหน้าที่ร่วมเดินทาง</label>
                    <input
                      type="text"
                      className="form-control"
                      value={form.assistant1}
                      onChange={(e) => setForm({ ...form, assistant1: e.target.value })}
                    />
                  </div>
                </div>
                <div className="modal-footer bg-light">
                  <button type="button" className="btn btn-secondary" onClick={() => setShowModal(false)}>ยกเลิก</button>
                  <button type="submit" className="btn btn-primary-gradient px-4">ยืนยันจัดรถ</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
