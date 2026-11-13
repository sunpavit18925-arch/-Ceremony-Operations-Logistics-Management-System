"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";

export default function ManageDriversPage() {
  const { currentRole } = useDemo();
  const [drivers, setDrivers] = useState([]);
  const [vehicleTypes, setVehicleTypes] = useState([]);
  const [driverQuals, setDriverQuals] = useState([]);
  const [activeDriver, setActiveDriver] = useState(null);
  const [selectedVehicles, setSelectedVehicles] = useState([]);
  const [alertMsg, setAlertMsg] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [loading, setLoading] = useState(true);

  const isHead = currentRole === "head_vehicle" || currentRole === "super_admin";

  const fetchData = async () => {
    try {
      const headers = { "x-demo-role": currentRole };
      const [userRes, typeRes, qualRes] = await Promise.all([
        fetch("http://localhost:4000/api/demo/users", { headers }),
        fetch("http://localhost:4000/api/demo/vehicle-types", { headers }),
        fetch("http://localhost:4000/api/demo/drivers/qualifications", { headers })
      ]);

      const userData = await userRes.json();
      const typeData = await typeRes.json();
      const qualData = await qualRes.json();

      if (userData.success) {
        setDrivers(userData.data.filter(u => u.role === "staff_vehicle" || u.role === "head_vehicle"));
      }
      if (typeData.success) setVehicleTypes(typeData.data);
      if (qualData.success) setDriverQuals(qualData.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [currentRole]);

  const openModal = (driver) => {
    setActiveDriver(driver);
    const qual = driverQuals.find(q => q.userId === driver.id);
    setSelectedVehicles(qual ? qual.allowedVehicles : []);
    setShowModal(true);
  };

  const handleCheckboxChange = (vType) => {
    if (selectedVehicles.includes(vType)) {
      setSelectedVehicles(selectedVehicles.filter(t => t !== vType));
    } else {
      setSelectedVehicles([...selectedVehicles, vType]);
    }
  };

  const handleSaveQualifications = async (e) => {
    e.preventDefault();
    if (!activeDriver) return;

    try {
      const res = await fetch("http://localhost:4000/api/demo/drivers/qualifications", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({
          userId: activeDriver.id,
          allowedVehicles: selectedVehicles
        })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg(data.message);
        setShowModal(false);
        fetchData();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  return (
    <div className="content-wrapper bg-light" style={{ minHeight: "100vh" }}>
      <div className="container py-5">
        {/* Header matching PHP manage_drivers.php */}
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
            <h2 className="fw-bold mb-0">จัดการคุณสมบัติพนักงานขับรถ</h2>
            <p className="text-muted mt-2 mb-0">กำหนดประเภทรถที่พนักงานขับรถแต่ละคนสามารถขับได้</p>
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

        <div className="card shadow-sm border-0" style={{ borderRadius: "1rem" }}>
          <div className="card-body p-0">
            <div className="table-responsive">
              <table className="table table-bordered table-hover align-middle mb-0" style={{ minWidth: "800px" }}>
                <thead className="table-dark">
                  <tr>
                    <th style={{ width: "5%" }}>ลำดับ</th>
                    <th style={{ width: "25%" }}>ชื่อ - นามสกุล</th>
                    <th style={{ width: "15%" }}>ตำแหน่ง/สิทธิ์</th>
                    <th style={{ width: isHead ? "40%" : "55%" }}>ประเภทรถที่ขับได้ (ใบอนุญาต)</th>
                    {isHead && (
                      <th style={{ width: "15%" }} className="text-center">จัดการ</th>
                    )}
                  </tr>
                </thead>
                <tbody>
                  {drivers.length === 0 ? (
                    <tr>
                      <td colSpan={isHead ? 5 : 4} className="text-center text-muted py-4">
                        ไม่พบบุคลากรฝ่ายยานยนต์ในระบบ
                      </td>
                    </tr>
                  ) : (
                    drivers.map((driver, index) => {
                      const qual = driverQuals.find(q => q.userId === driver.id);
                      const allowed = qual ? qual.allowedVehicles : [];
                      return (
                        <tr key={driver.id}>
                          <td className="text-center">{index + 1}</td>
                          <td className="fw-semibold text-dark">{driver.name}</td>
                          <td>
                            {driver.role === "head_vehicle" ? (
                              <span className="badge bg-warning text-dark">หัวหน้าฝ่ายยานยนต์</span>
                            ) : (
                              <span className="badge bg-secondary">ฝ่ายยานยนต์</span>
                            )}
                          </td>
                          <td>
                            {allowed.length > 0 ? (
                              allowed.map(vq => (
                                <span key={vq} className="badge bg-primary me-1 mb-1">
                                  {vq}
                                </span>
                              ))
                            ) : (
                              <span className="text-muted small">ยังไม่กำหนดประเภทรถ</span>
                            )}
                          </td>
                          {isHead && (
                            <td className="text-center">
                              <button
                                type="button"
                                onClick={() => openModal(driver)}
                                className="btn btn-warning btn-sm text-dark fw-bold rounded-pill px-3 shadow-sm"
                              >
                                กำหนดใบอนุญาต
                              </button>
                            </td>
                          )}
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      {/* Manage Driver Modal */}
      {showModal && activeDriver && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <form onSubmit={handleSaveQualifications}>
                <div className="modal-header border-bottom bg-light px-4 py-3">
                  <h5 className="modal-title fw-bold text-dark">
                    <i className="bi bi-person-gear text-warning me-2"></i>กำหนดคุณสมบัติการขับรถ
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setShowModal(false)}></button>
                </div>
                <div className="modal-body p-4">
                  <h6 className="fw-bold mb-3 text-primary">
                    เจ้าหน้าที่: {activeDriver.name}
                  </h6>
                  <p className="text-muted small mb-3">
                    โปรดเลือกประเภทรถที่เจ้าหน้าที่ผู้นี้ได้รับอนุญาตให้ขับขี่ได้ (สามารถเลือกได้มากกว่า 1 ประเภท):
                  </p>

                  <div className="bg-light p-3 rounded-3 border">
                    {vehicleTypes.map(type => (
                      <div key={type} className="form-check mb-2">
                        <input
                          className="form-check-input"
                          type="checkbox"
                          id={`check_${type}`}
                          checked={selectedVehicles.includes(type)}
                          onChange={() => handleCheckboxChange(type)}
                        />
                        <label className="form-check-label fw-medium text-dark" htmlFor={`check_${type}`}>
                          {type}
                        </label>
                      </div>
                    ))}
                  </div>
                </div>
                <div className="modal-footer bg-light border-0">
                  <button type="button" className="btn btn-secondary rounded-pill px-3" onClick={() => setShowModal(false)}>
                    ยกเลิก
                  </button>
                  <button type="submit" className="btn btn-warning rounded-pill px-4 text-dark fw-bold">
                    บันทึกคุณสมบัติ
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


