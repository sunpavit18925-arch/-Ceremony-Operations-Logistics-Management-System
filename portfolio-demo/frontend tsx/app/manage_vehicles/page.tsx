"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";

export default function ManageVehiclesPage() {
  const { currentRole } = useDemo();
  const [vehicles, setVehicles] = useState([]);
  const [vehicleTypes, setVehicleTypes] = useState([]);
  const [selectedTypeFilter, setSelectedTypeFilter] = useState("all");
  const [searchQuery, setSearchQuery] = useState("");
  const [loading, setLoading] = useState(true);
  const [alertMsg, setAlertMsg] = useState(null);

  // Modals
  const [showAddModal, setShowAddModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [showTypeModal, setShowTypeModal] = useState(false);
  const [activeVehicle, setActiveVehicle] = useState(null);

  // Add/Edit Form state
  const [formType, setFormType] = useState("");
  const [formBrand, setFormBrand] = useState("");
  const [formLetters, setFormLetters] = useState("");
  const [formNumbers, setFormNumbers] = useState("");
  const [formProvince, setFormProvince] = useState("กรุงเทพมหานคร");
  const [formDriver, setFormDriver] = useState("");

  // Manage Types state
  const [newTypeName, setNewTypeName] = useState("");

  const fetchData = async () => {
    try {
      const headers = { "x-demo-role": currentRole };
      const [vehRes, typeRes] = await Promise.all([
        fetch("http://localhost:4000/api/demo/vehicles", { headers }),
        fetch("http://localhost:4000/api/demo/vehicle-types", { headers })
      ]);

      const vehData = await vehRes.json();
      const typeData = await typeRes.json();

      if (vehData.success) setVehicles(vehData.data);
      if (typeData.success) {
        setVehicleTypes(typeData.data);
        if (typeData.data.length > 0 && !formType) {
          setFormType(typeData.data[0]);
        }
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

  const handleAddVehicle = async (e) => {
    e.preventDefault();
    try {
      const res = await fetch("http://localhost:4000/api/demo/vehicles", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({
          vehicleType: formType,
          brand: formBrand,
          plateLetters: formLetters,
          plateNumbers: formNumbers,
          plateProvince: formProvince,
          currentDriver: formDriver
        })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg(data.message);
        setShowAddModal(false);
        setFormBrand("");
        setFormLetters("");
        setFormNumbers("");
        fetchData();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  const handleEditVehicle = async (e) => {
    e.preventDefault();
    if (!activeVehicle) return;

    try {
      const res = await fetch(`http://localhost:4000/api/demo/vehicles/${activeVehicle.id}`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({
          vehicleType: formType,
          brand: formBrand,
          plateLetters: formLetters,
          plateNumbers: formNumbers,
          plateProvince: formProvince,
          currentDriver: formDriver
        })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg(data.message);
        setShowEditModal(false);
        setActiveVehicle(null);
        fetchData();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  const handleDeleteVehicle = async (id, plate) => {
    if (!window.confirm(`ยืนยันการลบรถทะเบียน ${plate} ออกจากระบบ?`)) return;

    try {
      const res = await fetch(`http://localhost:4000/api/demo/vehicles/${id}`, {
        method: "DELETE",
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

  const handleAddType = async (e) => {
    e.preventDefault();
    if (!newTypeName.trim()) return;

    try {
      const res = await fetch("http://localhost:4000/api/demo/vehicle-types", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "x-demo-role": currentRole
        },
        body: JSON.stringify({ typeName: newTypeName.trim() })
      });
      const data = await res.json();
      if (data.success) {
        setAlertMsg(data.message);
        setNewTypeName("");
        fetchData();
      } else {
        alert(data.error || "เกิดข้อผิดพลาด");
      }
    } catch (err) {
      alert("เกิดข้อผิดพลาดในการเชื่อมต่อ API");
    }
  };

  const handleDeleteType = async (typeName) => {
    if (!window.confirm(`ยืนยันการลบประเภทรถ "${typeName}"?`)) return;

    try {
      const res = await fetch(`http://localhost:4000/api/demo/vehicle-types/${encodeURIComponent(typeName)}`, {
        method: "DELETE",
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

  const openEdit = (vehicle) => {
    setActiveVehicle(vehicle);
    setFormType(vehicle.vehicleType);
    setFormBrand(vehicle.brand);
    setFormLetters(vehicle.plateLetters || "");
    setFormNumbers(vehicle.plateNumbers || "");
    setFormProvince(vehicle.plateProvince || "กรุงเทพมหานคร");
    setFormDriver(vehicle.currentDriver || "");
    setShowEditModal(true);
  };

  const filteredVehicles = vehicles.filter(v => {
    if (selectedTypeFilter !== "all" && v.vehicleType !== selectedTypeFilter) {
      return false;
    }
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      return (
        v.licensePlate.toLowerCase().includes(q) ||
        v.brand.toLowerCase().includes(q) ||
        v.currentDriver?.toLowerCase().includes(q)
      );
    }
    return true;
  });

  return (
    <div className="content-wrapper" style={{ backgroundColor: "var(--app-page-bg)", minHeight: "100vh" }}>
      {/* Page Header matching PHP manage_vehicles.php */}
      <div
        className="page-header text-center mt-0"
        style={{
          background: "linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)",
          color: "white",
          padding: "2.5rem 0",
          boxShadow: "0 4px 15px rgba(0,0,0,0.1)",
          marginBottom: "2rem"
        }}
      >
        <div className="container position-relative text-start">
          <div className="row align-items-center">
            <div className="col-12 col-md-2 text-md-start mb-3 mb-md-0">
              <Link href="/home_head_vehicle" className="btn btn-outline-light rounded-pill d-inline-flex align-items-center">
                <i className="bi bi-arrow-left me-2"></i>ย้อนกลับ
              </Link>
            </div>
            <div className="col-12 col-md-8 text-center">
              <h2 className="fw-bold mb-2">
                <i className="bi bi-gear-fill me-2"></i>จัดการข้อมูลยานพาหนะ
              </h2>
              <p className="text-white-50 mb-0">เพิ่ม ลบ แก้ไข ประเภทรถและทะเบียนรถในระบบ</p>
            </div>
            <div className="col-12 col-md-2"></div>
          </div>
        </div>
      </div>

      <div className="container pb-5">
        {alertMsg && (
          <div className="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center alert-dismissible">
            <i className="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
            <div><h6 className="mb-0 fw-bold">{alertMsg}</h6></div>
            <button type="button" className="btn-close ms-auto" onClick={() => setAlertMsg(null)}></button>
          </div>
        )}

        <div className="card custom-card mb-4" style={{ borderRadius: "1.25rem", border: "none", boxShadow: "0 0.5rem 1.5rem rgba(0,0,0,0.05)", background: "var(--app-surface)" }}>
          <div className="card-header bg-white p-4 border-bottom shadow-sm">
            <div className="row align-items-center gy-3">
              <div className="col-md-8 d-flex align-items-center flex-wrap gap-2">
                <label className="fw-bold text-secondary me-2 mb-0">ตัวกรอง :</label>
                <button
                  type="button"
                  onClick={() => setSelectedTypeFilter("all")}
                  className={`btn btn-sm rounded-pill px-3 ${selectedTypeFilter === "all" ? "btn-dark" : "btn-outline-secondary"}`}
                >
                  ทั้งหมด ({vehicles.length})
                </button>
                {vehicleTypes.map(type => (
                  <button
                    key={type}
                    type="button"
                    onClick={() => setSelectedTypeFilter(type)}
                    className={`btn btn-sm rounded-pill px-3 ${selectedTypeFilter === type ? "btn-primary" : "btn-outline-secondary"}`}
                  >
                    {type} ({vehicles.filter(v => v.vehicleType === type).length})
                  </button>
                ))}
              </div>
              <div className="col-md-4 text-md-end d-flex justify-content-md-end gap-2">
                <button
                  type="button"
                  onClick={() => setShowTypeModal(true)}
                  className="btn btn-outline-secondary rounded-pill px-3 btn-sm"
                >
                  <i className="bi bi-sliders me-1"></i>ประเภทรถ
                </button>
                <button
                  type="button"
                  onClick={() => setShowAddModal(true)}
                  className="btn btn-primary rounded-pill px-3 btn-sm text-white"
                  style={{ background: "linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)", border: "none" }}
                >
                  <i className="bi bi-plus-circle me-1"></i>เพิ่มยานพาหนะ
                </button>
              </div>
            </div>
          </div>

          <div className="p-3 bg-light border-bottom">
            <div className="position-relative" style={{ maxWidth: "400px" }}>
              <span className="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                <i className="bi bi-search"></i>
              </span>
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="form-control ps-5 py-2"
                style={{ borderRadius: "0.75rem" }}
                placeholder="ค้นหาทะเบียนรถ, ยี่ห้อ..."
              />
            </div>
          </div>

          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th style={{ width: "20%" }} className="ps-4">ทะเบียนรถ</th>
                  <th style={{ width: "20%" }}>ยี่ห้อ / รุ่น</th>
                  <th style={{ width: "25%" }}>ประเภทรถ</th>
                  <th style={{ width: "20%" }}>คนขับประจำรถ</th>
                  <th style={{ width: "15%" }} className="text-center">จัดการ</th>
                </tr>
              </thead>
              <tbody>
                {filteredVehicles.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="text-center py-5 text-muted">
                      ไม่พบข้อมูลยานพาหนะในระบบ
                    </td>
                  </tr>
                ) : (
                  filteredVehicles.map(veh => (
                    <tr key={veh.id}>
                      <td className="ps-4">
                        <span className="badge bg-dark bg-opacity-10 text-dark border px-3 py-2 fs-6 fw-bold">
                          {veh.licensePlate}
                        </span>
                      </td>
                      <td className="fw-semibold text-dark">{veh.brand}</td>
                      <td>
                        <span className="badge bg-info bg-opacity-10 text-info border border-info rounded-pill px-3 py-1">
                          {veh.vehicleType}
                        </span>
                      </td>
                      <td>
                        <div className="d-flex align-items-center gap-2">
                          <i className="bi bi-person-fill text-success"></i>
                          <span>{veh.currentDriver || "วิชัย ขับปลอดภัย"}</span>
                        </div>
                      </td>
                      <td className="text-center">
                        <button
                          type="button"
                          onClick={() => openEdit(veh)}
                          className="btn btn-sm btn-outline-warning text-dark rounded-pill px-3 me-2"
                        >
                          <i className="bi bi-pencil me-1"></i>แก้ไข
                        </button>
                        <button
                          type="button"
                          onClick={() => handleDeleteVehicle(veh.id, veh.licensePlate)}
                          className="btn btn-sm btn-outline-danger rounded-pill px-3"
                        >
                          <i className="bi bi-trash me-1"></i>ลบ
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

      {/* Add Vehicle Modal */}
      {showAddModal && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <form onSubmit={handleAddVehicle}>
                <div className="modal-header border-bottom bg-light px-4 py-3">
                  <h5 className="modal-title fw-bold text-dark">
                    <i className="bi bi-plus-circle text-primary me-2"></i>เพิ่มยานพาหนะใหม่
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setShowAddModal(false)}></button>
                </div>
                <div className="modal-body p-4">
                  <div className="mb-3">
                    <label className="form-label small fw-bold text-secondary">ประเภทรถ:</label>
                    <select
                      className="form-select"
                      value={formType}
                      onChange={(e) => setFormType(e.target.value)}
                      required
                    >
                      {vehicleTypes.map(t => (
                        <option key={t} value={t}>{t}</option>
                      ))}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label small fw-bold text-secondary">ยี่ห้อ / รุ่น:</label>
                    <input
                      type="text"
                      className="form-control"
                      placeholder="เช่น Toyota Commuter"
                      value={formBrand}
                      onChange={(e) => setFormBrand(e.target.value)}
                      required
                    />
                  </div>
                  <div className="row g-2 mb-3">
                    <div className="col-4">
                      <label className="form-label small fw-bold text-secondary">หมวดอักษร:</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="เช่น นข"
                        value={formLetters}
                        onChange={(e) => setFormLetters(e.target.value)}
                        required
                      />
                    </div>
                    <div className="col-4">
                      <label className="form-label small fw-bold text-secondary">เลขทะเบียน:</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="เช่น 4589"
                        value={formNumbers}
                        onChange={(e) => setFormNumbers(e.target.value)}
                        required
                      />
                    </div>
                    <div className="col-4">
                      <label className="form-label small fw-bold text-secondary">จังหวัด:</label>
                      <input
                        type="text"
                        className="form-control"
                        value={formProvince}
                        onChange={(e) => setFormProvince(e.target.value)}
                        required
                      />
                    </div>
                  </div>
                  <div className="mb-3">
                    <label className="form-label small fw-bold text-secondary">พนักงานขับรถประจำ:</label>
                    <input
                      type="text"
                      className="form-control"
                      placeholder="ชื่อ-นามสกุล คนขับประจำรถ"
                      value={formDriver}
                      onChange={(e) => setFormDriver(e.target.value)}
                    />
                  </div>
                </div>
                <div className="modal-footer bg-light border-0">
                  <button type="button" className="btn btn-secondary rounded-pill px-3" onClick={() => setShowAddModal(false)}>
                    ยกเลิก
                  </button>
                  <button type="submit" className="btn btn-primary rounded-pill px-4 text-white">
                    บันทึกข้อมูลรถ
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Edit Vehicle Modal */}
      {showEditModal && activeVehicle && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <form onSubmit={handleEditVehicle}>
                <div className="modal-header border-bottom bg-light px-4 py-3">
                  <h5 className="modal-title fw-bold text-dark">
                    <i className="bi bi-pencil text-warning me-2"></i>แก้ไขข้อมูลยานพาหนะ
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setShowEditModal(false)}></button>
                </div>
                <div className="modal-body p-4">
                  <div className="mb-3">
                    <label className="form-label small fw-bold text-secondary">ประเภทรถ:</label>
                    <select
                      className="form-select"
                      value={formType}
                      onChange={(e) => setFormType(e.target.value)}
                      required
                    >
                      {vehicleTypes.map(t => (
                        <option key={t} value={t}>{t}</option>
                      ))}
                    </select>
                  </div>
                  <div className="mb-3">
                    <label className="form-label small fw-bold text-secondary">ยี่ห้อ / รุ่น:</label>
                    <input
                      type="text"
                      className="form-control"
                      value={formBrand}
                      onChange={(e) => setFormBrand(e.target.value)}
                      required
                    />
                  </div>
                  <div className="row g-2 mb-3">
                    <div className="col-4">
                      <label className="form-label small fw-bold text-secondary">หมวดอักษร:</label>
                      <input
                        type="text"
                        className="form-control"
                        value={formLetters}
                        onChange={(e) => setFormLetters(e.target.value)}
                        required
                      />
                    </div>
                    <div className="col-4">
                      <label className="form-label small fw-bold text-secondary">เลขทะเบียน:</label>
                      <input
                        type="text"
                        className="form-control"
                        value={formNumbers}
                        onChange={(e) => setFormNumbers(e.target.value)}
                        required
                      />
                    </div>
                    <div className="col-4">
                      <label className="form-label small fw-bold text-secondary">จังหวัด:</label>
                      <input
                        type="text"
                        className="form-control"
                        value={formProvince}
                        onChange={(e) => setFormProvince(e.target.value)}
                        required
                      />
                    </div>
                  </div>
                  <div className="mb-3">
                    <label className="form-label small fw-bold text-secondary">คนขับประจำรถ:</label>
                    <input
                      type="text"
                      className="form-control"
                      value={formDriver}
                      onChange={(e) => setFormDriver(e.target.value)}
                    />
                  </div>
                </div>
                <div className="modal-footer bg-light border-0">
                  <button type="button" className="btn btn-secondary rounded-pill px-3" onClick={() => setShowEditModal(false)}>
                    ยกเลิก
                  </button>
                  <button type="submit" className="btn btn-warning rounded-pill px-4 text-dark fw-bold">
                    บันทึกการแก้ไข
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* Manage Vehicle Types Modal */}
      {showTypeModal && (
        <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <div className="modal-header border-bottom bg-light px-4 py-3">
                <h5 className="modal-title fw-bold text-dark">
                  <i className="bi bi-sliders text-primary me-2"></i>จัดการประเภทรถราชการ
                </h5>
                <button type="button" className="btn-close" onClick={() => setShowTypeModal(false)}></button>
              </div>
              <div className="modal-body p-4">
                <form onSubmit={handleAddType} className="d-flex gap-2 mb-4">
                  <input
                    type="text"
                    className="form-control"
                    placeholder="เพิ่มประเภทรถใหม่..."
                    value={newTypeName}
                    onChange={(e) => setNewTypeName(e.target.value)}
                    required
                  />
                  <button type="submit" className="btn btn-success px-4 rounded-pill">
                    เพิ่ม
                  </button>
                </form>

                <h6 className="fw-bold small text-secondary mb-2">ประเภทรถในระบบ:</h6>
                <ul className="list-group">
                  {vehicleTypes.map(t => (
                    <li key={t} className="list-group-item d-flex justify-content-between align-items-center">
                      <span>{t}</span>
                      <button
                        type="button"
                        onClick={() => handleDeleteType(t)}
                        className="btn btn-sm btn-outline-danger border-0"
                        title="ลบประเภทรถ"
                      >
                        <i className="bi bi-trash"></i>
                      </button>
                    </li>
                  ))}
                </ul>
              </div>
              <div className="modal-footer bg-light border-0">
                <button type="button" className="btn btn-secondary rounded-pill px-4" onClick={() => setShowTypeModal(false)}>
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


