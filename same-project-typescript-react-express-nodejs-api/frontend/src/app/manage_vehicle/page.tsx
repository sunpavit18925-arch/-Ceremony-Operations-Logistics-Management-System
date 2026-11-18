"use client";

import React, { useState, useEffect, useCallback, Suspense } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useDemo } from "@/context/DemoContext";

function ManageVehicleContent() {
  const searchParams = useSearchParams();
  const jobId = searchParams.get("job_id") || "1";
  const { currentRole } = useDemo();

  const [document, setDocument] = useState(null);
  const [vehicles, setVehicles] = useState([]);
  const [drivers, setDrivers] = useState([]);
  const [assignments, setAssignments] = useState([]);
  const [selectedVehicleId, setSelectedVehicleId] = useState("");
  const [selectedDriverName, setSelectedDriverName] = useState("");
  const [assistant1, setAssistant1] = useState("");
  const [assistant2, setAssistant2] = useState("");
  const [alertMsg, setAlertMsg] = useState(null);
  const [loading, setLoading] = useState(true);

  const fetchData = useCallback(() => {
    const load = async () => {
      const headers = { "x-demo-role": currentRole };
      const [docRes, vehRes, userRes] = await Promise.all([
        fetch(`http://localhost:4000/api/demo/documents/${jobId}`, { headers }),
        fetch("http://localhost:4000/api/demo/vehicles", { headers }),
        fetch("http://localhost:4000/api/demo/users", { headers })
      ]);

      const docData = await docRes.json();
      const vehData = await vehRes.json();
      const userData = await userRes.json();
      return { docData, vehData, userData };
    };
    return load().then(({ docData, vehData, userData }) => {
      if (docData.success) {
        setDocument(docData.data);
        setAssignments(docData.vehicleAssignments || []);
      }
      if (vehData.success) setVehicles(vehData.data);
      if (userData.success) {
        setDrivers(userData.data.filter(u => u.role === "staff_vehicle" || u.role === "head_vehicle"));
      }
    }).catch((err) => {
      console.error(err);
    }).finally(() => {
      setLoading(false);
    });
  }, [currentRole, jobId]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleAssignVehicle = async (e) => {
    e.preventDefault();
    if (!selectedVehicleId || !selectedDriverName) return;

    const chosenVeh = vehicles.find(v => v.id === parseInt(selectedVehicleId));
    if (!chosenVeh) return;

    try {
      const res = await fetch("http://localhost:4000/api/demo/vehicles/assign", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({
          docId: parseInt(jobId),
          vehicleType: chosenVeh.vehicleType,
          licensePlate: chosenVeh.licensePlate,
          driverName: selectedDriverName,
          assistant1: assistant1 || "-",
          assistant2: assistant2 || "-"
        })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg(data.message);
        setSelectedVehicleId("");
        setSelectedDriverName("");
        setAssistant1("");
        setAssistant2("");
        fetchData();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  if (!document && !loading) {
    return (
      <div className="container py-5 text-center">
        <h4>ไม่พบข้อมูลภารกิจรหัสนี้</h4>
        <Link href="/select_mission" className="btn btn-primary mt-3">
          กลับสู่หน้ารายการภารกิจ
        </Link>
      </div>
    );
  }

  return (
    <div className="content-wrapper" style={{ backgroundColor: "var(--app-muted-bg)", minHeight: "100vh" }}>
      <div className="container py-5">
        {/* Back Link */}
        <div className="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
          <Link
            href="/select_mission"
            className="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center"
          >
            <i className="bi bi-arrow-left me-2"></i>ย้อนกลับไปรายการภารกิจ
          </Link>
          <h4 className="fw-bold mb-0 text-dark">
            <i className="bi bi-truck text-success me-2"></i>จัดยานพาหนะและพนักงานขับรถ
          </h4>
          <div></div>
        </div>

        {alertMsg && (
          <div className="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center alert-dismissible">
            <i className="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
            <div><h6 className="mb-0 fw-bold">{alertMsg}</h6></div>
            <button type="button" className="btn-close ms-auto" onClick={() => setAlertMsg(null)}></button>
          </div>
        )}

        {/* Mission Summary Card */}
        {document && (
          <div className="card shadow-sm border-0 mb-4" style={{ borderRadius: "1.25rem", borderLeft: "5px solid #10b981" }}>
            <div className="card-body p-4">
              <div className="row g-3">
                <div className="col-md-8">
                  <div className="small text-muted">ชื่องานภารกิจ:</div>
                  <h5 className="fw-bold text-primary mb-1">{document.docName}</h5>
                  <div className="small text-muted">
                    เลขที่: <strong>{document.docNumber}</strong> | หน่วยงาน: <strong>{document.hostAgency}</strong>
                  </div>
                </div>
                <div className="col-md-4 text-md-end">
                  <div className="small text-muted">กำหนดการเดินทาง:</div>
                  <div className="fw-bold text-dark">{document.startDate} ถึง {document.endDate}</div>
                  <span className="badge bg-info bg-opacity-10 text-info border border-info rounded-pill px-3 py-1 mt-1">
                    {document.teamSet || "จัดชุดแล้ว"}
                  </span>
                </div>
              </div>
            </div>
          </div>
        )}

        <div className="row g-4">
          {/* Left: Assign Form */}
          <div className="col-lg-5">
            <div className="card shadow-sm border-0 h-100" style={{ borderRadius: "1.25rem" }}>
              <div className="card-header bg-white border-bottom p-4">
                <h5 className="fw-bold mb-0 text-dark">
                  <i className="bi bi-plus-circle text-primary me-2"></i>เลือกยานพาหนะและคนขับ
                </h5>
              </div>
              <div className="card-body p-4">
                <form onSubmit={handleAssignVehicle}>
                  <div className="mb-3">
                    <label className="form-label small fw-bold text-secondary">เลือกยานพาหนะ:</label>
                    <select
                      className="form-select"
                      value={selectedVehicleId}
                      onChange={(e) => setSelectedVehicleId(e.target.value)}
                      required
                    >
                      <option value="" disabled>-- คลิกเลือกยานพาหนะ --</option>
                      {vehicles.map(v => (
                        <option key={v.id} value={v.id}>
                          {v.licensePlate} ({v.brand} - {v.vehicleType})
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="mb-3">
                    <label className="form-label small fw-bold text-secondary">พนักงานขับรถ:</label>
                    <select
                      className="form-select"
                      value={selectedDriverName}
                      onChange={(e) => setSelectedDriverName(e.target.value)}
                      required
                    >
                      <option value="" disabled>-- คลิกเลือกพนักงานขับรถ --</option>
                      {drivers.map(d => (
                        <option key={d.id} value={d.name}>
                          {d.name} ({d.role === "head_vehicle" ? "หัวหน้าฝ่ายยานยนต์" : "ฝ่ายยานยนต์"})
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="mb-3">
                    <label className="form-label small fw-bold text-secondary">ผู้ช่วยประจำรถ 1 (ถ้ามี):</label>
                    <input
                      type="text"
                      className="form-control"
                      placeholder="ชื่อ-นามสกุล ผู้ช่วย..."
                      value={assistant1}
                      onChange={(e) => setAssistant1(e.target.value)}
                    />
                  </div>

                  <div className="mb-4">
                    <label className="form-label small fw-bold text-secondary">ผู้ช่วยประจำรถ 2 (ถ้ามี):</label>
                    <input
                      type="text"
                      className="form-control"
                      placeholder="ชื่อ-นามสกุล ผู้ช่วย..."
                      value={assistant2}
                      onChange={(e) => setAssistant2(e.target.value)}
                    />
                  </div>

                  <button
                    type="submit"
                    className="btn btn-primary w-100 rounded-pill py-2 fw-bold text-white shadow-sm"
                    style={{ background: "linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)", border: "none" }}
                  >
                    <i className="bi bi-check-circle me-1"></i> ยืนยันการจัดรถ
                  </button>
                </form>
              </div>
            </div>
          </div>

          {/* Right: Assigned Vehicles List */}
          <div className="col-lg-7">
            <div className="card shadow-sm border-0 h-100" style={{ borderRadius: "1.25rem" }}>
              <div className="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                <h5 className="fw-bold mb-0 text-dark">
                  <i className="bi bi-list-check text-success me-2"></i>ยานพาหนะที่จัดสรรสำหรับภารกิจนี้
                </h5>
                <span className="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">
                  {assignments.length} คัน
                </span>
              </div>
              <div className="card-body p-4">
                {assignments.length === 0 ? (
                  <div className="text-center py-5 text-muted">
                    <i className="bi bi-truck fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    ยังไม่ได้จัดสรรยานพาหนะสำหรับภารกิจนี้
                  </div>
                ) : (
                  <div className="table-responsive">
                    <table className="table table-bordered align-middle">
                      <thead className="table-light">
                        <tr>
                          <th>ทะเบียนรถ</th>
                          <th>ประเภทรถ</th>
                          <th>พนักงานขับรถ</th>
                          <th>ผู้ช่วยประจำรถ</th>
                        </tr>
                      </thead>
                      <tbody>
                        {assignments.map(a => (
                          <tr key={a.id}>
                            <td className="fw-bold text-dark">{a.licensePlate}</td>
                            <td><span className="badge bg-light text-dark border">{a.vehicleType}</span></td>
                            <td className="fw-semibold text-primary">{a.driverName}</td>
                            <td className="small text-muted">{a.assistant1 || "-"}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function ManageVehiclePage() {
  return (
    <Suspense fallback={<div className="text-center p-5">Loading...</div>}>
      <ManageVehicleContent />
    </Suspense>
  );
}

