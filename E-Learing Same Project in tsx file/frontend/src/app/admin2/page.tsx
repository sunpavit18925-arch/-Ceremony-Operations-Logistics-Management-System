"use client";

import React, { useState, useEffect, useMemo } from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";
import { api } from "@/services/api";

interface ProvinceData {
  id: number;
  name_th: string;
  name_en: string;
  districts: {
    id: number;
    name_th: string;
    name_en: string;
    sub_districts: {
      id: number;
      name_th: string;
      name_en: string;
      zip_code?: number;
    }[];
  }[];
}

export default function Admin2Page() {
  const { currentRole, isInitialized } = useDemo();
  const [docs, setDocs] = useState<any[]>([]);
  const [search, setSearch] = useState("");
  const [successMsg, setSuccessMsg] = useState("");
  const [errorMsg, setErrorMsg] = useState("");

  // Thai Province Data
  const [thaiData, setThaiData] = useState<ProvinceData[]>([]);

  // Modals state
  const [showAddModal, setShowAddModal] = useState(false);
  const [viewingDoc, setViewingDoc] = useState<any>(null);
  const [editingDoc, setEditingDoc] = useState<any>(null);

  // Compute default fiscal year matching PHP
  const defaultFiscalYear = useMemo(() => {
    const d = new Date();
    const currentMonth = d.getMonth() + 1;
    const currentYearBE = d.getFullYear() + 543;
    return currentMonth >= 10 ? currentYearBE + 1 : currentYearBE;
  }, []);

  // Add form data matching PHP admin2.php (all 5 sections)
  const initialFormData = {
    fiscal_year: String(defaultFiscalYear),
    dpt_receive_number: "",
    dpt_receive_date: "",
    division_receive_number: "",
    division_receive_date: "",
    host_agency: "",
    host_agency_affiliation: "",
    source_doc_number: "",
    job_type: "",
    job_type_other: "",
    doc_name: "",
    description: "",
    venue_name: "",
    venue_province: "",
    venue_district: "",
    venue_subdistrict: "",
    event_start_date: "",
    event_end_date: "",
    pre_meeting_date: "",
    equipment_borrow_date: "",
    dpt_work_start: "",
    dpt_work_end: "",
    attachment_file: "",
    remark: "",
  };

  const [formData, setFormData] = useState(initialFormData);

  // Load Thailand provinces
  useEffect(() => {
    fetch("/thai-province-data-master/api/latest/province_with_district_and_sub_district.json")
      .then((res) => {
        if (!res.ok) throw new Error("Load province failed");
        return res.json();
      })
      .then((data) => {
        setThaiData(data);
      })
      .catch((err) => {
        console.error("Error loading thai provinces:", err);
      });
  }, []);

  const fetchDocs = async () => {
    const res = await api.getDocuments({}, currentRole);
    if (res.success) {
      setDocs(res.data);
    }
  };

  useEffect(() => {
    if (!isInitialized) return;
    fetchDocs();
    const handleReset = () => fetchDocs();
    window.addEventListener("demo-data-reset", handleReset);
    return () => window.removeEventListener("demo-data-reset", handleReset);
  }, [currentRole, isInitialized]);

  // Province dropdown helpers for Add Modal
  const addDistricts = useMemo(() => {
    if (!formData.venue_province || !thaiData.length) return [];
    const p = thaiData.find((item) => item.name_th === formData.venue_province);
    return p && p.districts ? [...p.districts].sort((a, b) => a.name_th.localeCompare(b.name_th, "th")) : [];
  }, [formData.venue_province, thaiData]);

  const addSubdistricts = useMemo(() => {
    if (!formData.venue_district || !addDistricts.length) return [];
    const d = addDistricts.find((item) => item.name_th === formData.venue_district);
    return d && d.sub_districts ? [...d.sub_districts].sort((a, b) => a.name_th.localeCompare(b.name_th, "th")) : [];
  }, [formData.venue_district, addDistricts]);

  // Province dropdown helpers for Edit Modal
  const editDistricts = useMemo(() => {
    if (!editingDoc || !editingDoc.venue_province || !thaiData.length) return [];
    const p = thaiData.find((item) => item.name_th === editingDoc.venue_province);
    return p && p.districts ? [...p.districts].sort((a, b) => a.name_th.localeCompare(b.name_th, "th")) : [];
  }, [editingDoc?.venue_province, thaiData]);

  const editSubdistricts = useMemo(() => {
    if (!editingDoc || !editingDoc.venue_district || !editDistricts.length) return [];
    const d = editDistricts.find((item) => item.name_th === editingDoc.venue_district);
    return d && d.sub_districts ? [...d.sub_districts].sort((a, b) => a.name_th.localeCompare(b.name_th, "th")) : [];
  }, [editingDoc?.venue_district, editDistricts]);

  const handleAddSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const payload = {
      fiscalYear: formData.fiscal_year,
      dptReceiveNumber: formData.dpt_receive_number,
      dptReceiveDate: formData.dpt_receive_date,
      divisionReceiveNumber: formData.division_receive_number || `มท 0701/${Math.floor(100 + Math.random() * 900)}`,
      divisionReceiveDate: formData.division_receive_date || new Date().toISOString().split("T")[0],
      hostAgency: formData.host_agency,
      hostAgencyAffiliation: formData.host_agency_affiliation,
      sourceDocNumber: formData.source_doc_number,
      jobType: formData.job_type === "อื่นๆ" ? (formData.job_type_other || "อื่นๆ") : formData.job_type,
      jobTypeOther: formData.job_type === "อื่นๆ" ? formData.job_type_other : "",
      docName: formData.doc_name,
      description: formData.description,
      venueName: formData.venue_name,
      venueProvince: formData.venue_province,
      venueDistrict: formData.venue_district,
      venueSubdistrict: formData.venue_subdistrict,
      eventStartDate: formData.event_start_date,
      eventEndDate: formData.event_end_date,
      preMeetingDate: formData.pre_meeting_date,
      equipmentBorrowDate: formData.equipment_borrow_date,
      dptWorkStart: formData.dpt_work_start,
      dptWorkEnd: formData.dpt_work_end,
      attachmentFile: formData.attachment_file,
      remark: formData.remark,
      startDate: formData.event_start_date || formData.dpt_work_start || new Date().toISOString().split("T")[0],
      endDate: formData.event_end_date || formData.dpt_work_end || new Date().toISOString().split("T")[0],
    };

    const res = await api.createDocument(payload, currentRole);
    if (res.success) {
      setSuccessMsg("บันทึกข้อมูลเข้าระบบเรียบร้อยแล้ว");
      setShowAddModal(false);
      setFormData(initialFormData);
      fetchDocs();
      setTimeout(() => setSuccessMsg(""), 3000);
    } else {
      setErrorMsg(res.message || res.error || "เกิดข้อผิดพลาดในการบันทึก");
      setTimeout(() => setErrorMsg(""), 3000);
    }
  };

  const handleEditSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingDoc) return;

    const payload = {
      ...editingDoc,
      jobType: editingDoc.job_type === "อื่นๆ" ? (editingDoc.job_type_other || "อื่นๆ") : editingDoc.job_type,
      fiscalYear: editingDoc.fiscal_year,
      dptReceiveNumber: editingDoc.dpt_receive_number,
      dptReceiveDate: editingDoc.dpt_receive_date,
      divisionReceiveNumber: editingDoc.division_receive_number,
      divisionReceiveDate: editingDoc.division_receive_date,
      hostAgency: editingDoc.host_agency,
      hostAgencyAffiliation: editingDoc.host_agency_affiliation,
      sourceDocNumber: editingDoc.source_doc_number,
      docName: editingDoc.doc_name,
      description: editingDoc.description,
      venueName: editingDoc.venue_name,
      venueProvince: editingDoc.venue_province,
      venueDistrict: editingDoc.venue_district,
      venueSubdistrict: editingDoc.venue_subdistrict,
      eventStartDate: editingDoc.event_start_date,
      eventEndDate: editingDoc.event_end_date,
      preMeetingDate: editingDoc.pre_meeting_date,
      equipmentBorrowDate: editingDoc.equipment_borrow_date,
      dptWorkStart: editingDoc.dpt_work_start,
      dptWorkEnd: editingDoc.dpt_work_end,
      attachmentFile: editingDoc.attachment_file,
      remark: editingDoc.remark,
    };

    const res = await api.updateDocument(editingDoc.id, payload, currentRole);
    if (res.success) {
      setSuccessMsg("แก้ไขข้อมูลในระบบเรียบร้อยแล้ว");
      setEditingDoc(null);
      fetchDocs();
      setTimeout(() => setSuccessMsg(""), 3000);
    } else {
      setErrorMsg(res.message || res.error || "เกิดข้อผิดพลาดในการแก้ไข");
      setTimeout(() => setErrorMsg(""), 3000);
    }
  };

  const handleDelete = async (id: number, docName: string) => {
    if (!confirm(`⚠️ คุณแน่ใจหรือไม่ว่าต้องการลบหนังสือ: \n\n"${docName}"\n\nการกระทำนี้ไม่สามารถย้อนกลับได้ ?`)) {
      return;
    }
    const res = await api.deleteDocument(id, currentRole);
    if (res.success) {
      setSuccessMsg("ลบข้อมูลเรียบร้อยแล้ว");
      fetchDocs();
      setTimeout(() => setSuccessMsg(""), 3000);
    } else {
      setErrorMsg(res.message || res.error || "เกิดข้อผิดพลาดในการลบ");
      setTimeout(() => setErrorMsg(""), 3000);
    }
  };

  const filteredDocs = docs.filter((d: any) => {
    if (!search.trim()) return true;
    const q = search.toLowerCase();
    const docNum = (d.divisionReceiveNumber || d.docNumber || "").toLowerCase();
    const dName = (d.docName || "").toLowerCase();
    const vName = (d.venueName || "").toLowerCase();
    const vProv = (d.venueProvince || "").toLowerCase();
    const sDocNum = (d.sourceDocNumber || "").toLowerCase();
    return (
      docNum.includes(q) ||
      dName.includes(q) ||
      vName.includes(q) ||
      vProv.includes(q) ||
      sDocNum.includes(q)
    );
  });

  const getBadgeClass = (jobType: string) => {
    switch (jobType) {
      case "รับเสด็จฯ":
        return "bg-danger text-white";
      case "ขอความอนุเคราะห์":
        return "bg-primary text-white";
      case "พิธีการ":
        return "bg-success text-white";
      case "วางศิลาฤกษ์":
        return "bg-warning text-dark";
      default:
        return "bg-secondary text-white";
    }
  };

  const formatDateTh = (dateStr?: string) => {
    if (!dateStr) return "-";
    try {
      const parts = dateStr.split("T")[0].split("-");
      if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
      }
      return dateStr;
    } catch {
      return dateStr;
    }
  };

  const openEditModal = (doc: any) => {
    const isOther = doc.jobType !== "รับเสด็จฯ" && doc.jobType !== "ขอความอนุเคราะห์" && !!doc.jobType;
    setEditingDoc({
      ...doc,
      fiscal_year: doc.fiscalYear || String(defaultFiscalYear),
      dpt_receive_number: doc.dptReceiveNumber || "",
      dpt_receive_date: doc.dptReceiveDate || "",
      division_receive_number: doc.divisionReceiveNumber || doc.docNumber || "",
      division_receive_date: doc.divisionReceiveDate || (doc.receivedDate ? doc.receivedDate.split("T")[0] : ""),
      host_agency: doc.hostAgency || "",
      host_agency_affiliation: doc.hostAgencyAffiliation || doc.hostAffiliation || "",
      source_doc_number: doc.sourceDocNumber || "",
      job_type: isOther ? "อื่นๆ" : (doc.jobType || ""),
      job_type_other: isOther ? (doc.jobTypeOther || doc.jobType) : "",
      doc_name: doc.docName || "",
      description: doc.description || "",
      venue_name: doc.venueName || "",
      venue_province: doc.venueProvince || "",
      venue_district: doc.venueDistrict || "",
      venue_subdistrict: doc.venueSubdistrict || "",
      event_start_date: doc.eventStartDate || doc.startDate || "",
      event_end_date: doc.eventEndDate || doc.endDate || "",
      pre_meeting_date: doc.preMeetingDate || "",
      equipment_borrow_date: doc.equipmentBorrowDate || "",
      dpt_work_start: doc.dptWorkStart || "",
      dpt_work_end: doc.dptWorkEnd || "",
      attachment_file: doc.attachmentFile || "",
      remark: doc.remark || "",
    });
  };

  return (
    <div style={{ backgroundColor: "var(--app-muted-bg)", minHeight: "100vh", display: "flex", flexDirection: "column" }}>
      {/* Page Header matching PHP admin2.php */}
      <div
        className="page-header"
        style={{
          background: "linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)",
          color: "white",
          padding: "2.5rem 0",
          marginBottom: "-3rem",
          borderRadius: "0 0 1.5rem 1.5rem",
          boxShadow: "0 4px 15px rgba(0,0,0,0.1)",
        }}
      >
        <div className="container">
          <div
            className="header-container"
            style={{
              display: "grid",
              gridTemplateColumns: "1fr auto 1fr",
              alignItems: "center",
            }}
          >
            <div className="text-start">
              <Link
                href="/home_admin"
                className="btn-back-rect"
                style={{
                  backgroundColor: "var(--app-surface)",
                  color: "var(--app-text)",
                  padding: "6px 16px",
                  borderRadius: "4px",
                  textDecoration: "none",
                  display: "inline-flex",
                  alignItems: "center",
                  fontSize: "0.9rem",
                  fontWeight: 500,
                  boxShadow: "0 2px 5px rgba(0,0,0,0.1)",
                }}
              >
                <i className="bi bi-chevron-left me-2"></i>ย้อนกลับ
              </Link>
            </div>
            <div className="text-center">
              <h3 className="fw-bold mb-1">
                <i className="bi bi-folder-check me-2"></i>ระบบรับงานหนังสือ
              </h3>
              <p className="mb-0 text-white-50" style={{ fontSize: "0.95rem" }}>
                {/* จัดการและติดตามสถานะงานหนังสือราชการ */}
              </p>
            </div>
            <div></div>
          </div>
        </div>
      </div>

      <div className="container" style={{ position: "relative", zIndex: 10, marginTop: "4.5rem", flex: 1 }}>
        {/* Flash Message */}
        {successMsg && (
          <div className="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
            <i className="bi bi-check-circle-fill me-2 fs-5"></i>
            {successMsg}
            <button type="button" className="btn-close" onClick={() => setSuccessMsg("")}></button>
          </div>
        )}
        {errorMsg && (
          <div className="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
            <i className="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            {errorMsg}
            <button type="button" className="btn-close" onClick={() => setErrorMsg("")}></button>
          </div>
        )}

        <div className="row justify-content-center">
          <div className="col-12">
            <div
              className="card custom-card mb-5"
              style={{
                border: "none",
                borderRadius: "1.25rem",
                boxShadow: "0 0.5rem 1.5rem rgba(0,0,0,0.08)",
                backgroundColor: "var(--app-surface)",
                overflow: "hidden",
              }}
            >
              <div className="card-header bg-white border-bottom-0 pt-4 pb-3 px-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <h5 className="fw-bold text-dark mb-0">
                  <i className="bi bi-list-columns-reverse me-2 text-primary"></i>รายการงานหนังสือที่รับ
                </h5>
                <div
                  className="d-flex flex-column flex-md-row gap-2 align-items-md-center w-100"
                  style={{ maxWidth: "450px", justifyContent: "flex-end" }}
                >
                  {/* Search Box */}
                  <div className="position-relative w-100">
                    <span className="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                      <i className="bi bi-search"></i>
                    </span>
                    <input
                      type="text"
                      className="form-control bg-light border-0 shadow-sm ps-5 py-2"
                      style={{ borderRadius: "20px" }}
                      placeholder="ค้นหาเลขรับ, ชื่องาน, สถานที่..."
                      value={search}
                      onChange={(e) => setSearch(e.target.value)}
                    />
                  </div>
                  <button
                    type="button"
                    className="btn btn-primary-gradient px-4 py-2 fw-bold rounded-pill shadow-sm text-nowrap"
                    onClick={() => setShowAddModal(true)}
                  >
                    <i className="bi bi-plus-lg me-1"></i> เพิ่ม
                  </button>
                </div>
              </div>

              <div className="card-body p-0">
                <div className="table-responsive bg-white rounded-3 shadow-sm border" style={{ WebkitOverflowScrolling: "touch" }}>
                  <table className="table table-hover table-custom mb-0 align-middle" style={{ tableLayout: "fixed", width: "100%", minWidth: "900px" }}>
                    <thead className="table-light">
                      <tr>
                        <th className="ps-4" style={{ width: "15%", minWidth: "140px" }}>เลขรับ / วันที่</th>
                        <th style={{ width: "20%", minWidth: "180px" }}>หนังสือต้นทาง</th>
                        <th style={{ width: "15%", minWidth: "140px" }}>ประเภทงาน</th>
                        <th style={{ width: "35%", minWidth: "300px" }}>ชื่องาน / รายละเอียด</th>
                        <th className="text-center pe-4" style={{ width: "15%", minWidth: "120px" }}>จัดการ</th>
                      </tr>
                    </thead>
                    <tbody>
                      {filteredDocs.length === 0 ? (
                        <tr>
                          <td colSpan={5} className="text-center py-5 text-muted border-0">
                            <i className="bi bi-search fs-1 d-block mb-3 opacity-50"></i>
                            <span className="fw-semibold">
                              {search.trim() ? "ไม่พบข้อมูลที่ค้นหา" : "ยังไม่มีข้อมูลในระบบ"}
                            </span>
                          </td>
                        </tr>
                      ) : (
                        filteredDocs.map((doc: any) => {
                          const isForwarded = doc.assignedToId || doc.status !== "pending";
                          return (
                            <tr key={doc.id} className="document-row">
                              <td className="ps-4">
                                <div className="d-flex flex-column">
                                  <span className="fw-bold text-dark mb-1">
                                    {doc.divisionReceiveNumber || doc.docNumber || "-"}
                                  </span>
                                  <small className="text-muted">
                                    <i className="bi bi-calendar3 me-1"></i>
                                    {formatDateTh(doc.divisionReceiveDate || doc.receivedDate)}
                                  </small>
                                </div>
                              </td>

                              <td style={{ wordBreak: "break-all" }}>
                                <div className="d-flex flex-column">
                                  <span className="text-primary fw-bold" style={{ fontSize: "0.9rem" }}>
                                    {doc.sourceDocNumber || "-"}
                                  </span>
                                  <small className="text-muted text-truncate" title={doc.hostAgency || ""}>
                                    {doc.hostAgency || "ไม่ระบุหน่วยงาน"}
                                  </small>
                                  {isForwarded ? (
                                    <div className="badge bg-success bg-opacity-10 text-success mt-1 d-inline-block text-start w-auto" style={{ fontSize: "0.7rem" }}>
                                      <i className="bi bi-check-circle-fill me-1"></i>มอบหมายคนแล้ว
                                    </div>
                                  ) : (
                                    <div className="badge bg-warning bg-opacity-10 text-warning mt-1 d-inline-block text-start w-auto" style={{ fontSize: "0.7rem" }}>
                                      <i className="bi bi-clock me-1"></i>รอมอบหมาย
                                    </div>
                                  )}
                                </div>
                              </td>

                              <td style={{ wordBreak: "break-all" }}>
                                <span className={`badge badge-custom ${getBadgeClass(doc.jobType)}`} style={{ whiteSpace: "normal", textAlign: "left", borderRadius: "2rem", padding: "0.5em 1em" }}>
                                  {doc.jobType || "-"}
                                </span>
                              </td>

                              <td style={{ wordBreak: "break-all" }}>
                                <div>
                                  <span className="text-dark fw-bold">{doc.docName || "ไม่ระบุชื่องาน"}</span>
                                </div>
                                {doc.description && (
                                  <div className="text-muted small mt-1 text-truncate" style={{ maxWidth: "300px" }} title={doc.description}>
                                    {doc.description}
                                  </div>
                                )}
                              </td>

                              <td className="text-center pe-4">
                                <div className="d-flex justify-content-center gap-2">
                                  {/* View Button */}
                                  <button
                                    type="button"
                                    className="btn-action btn-view border-0"
                                    onClick={() => setViewingDoc(doc)}
                                    title="ดูรายละเอียดทั้งหมด"
                                    style={{
                                      width: "35px",
                                      height: "35px",
                                      borderRadius: "0.5rem",
                                      display: "inline-flex",
                                      alignItems: "center",
                                      justifyContent: "center",
                                      backgroundColor: "var(--bs-info-bg-subtle)",
                                      color: "var(--bs-info-text-emphasis)",
                                      border: "1px solid var(--bs-info-border-subtle)"
                                    }}
                                  >
                                    <i className="bi bi-eye-fill"></i>
                                  </button>

                                  {/* Edit Button */}
                                  <button
                                    type="button"
                                    className="btn-action btn-edit border-0"
                                    onClick={() => openEditModal(doc)}
                                    title="แก้ไขรายละเอียดทั้งหมด"
                                    style={{
                                      width: "35px",
                                      height: "35px",
                                      borderRadius: "0.5rem",
                                      display: "inline-flex",
                                      alignItems: "center",
                                      justifyContent: "center",
                                      backgroundColor: "var(--bs-warning-bg-subtle)",
                                      color: "var(--bs-warning-text-emphasis)",
                                      border: "1px solid var(--bs-warning-border-subtle)"
                                    }}
                                  >
                                    <i className="bi bi-pencil-square"></i>
                                  </button>

                                  {/* Delete Button */}
                                  {isForwarded ? (
                                    <button
                                      type="button"
                                      className="btn-action btn-disabled border-0"
                                      title="มีการรับเรื่องหรือมอบหมายคนแล้ว ไม่สามารถลบได้"
                                      disabled
                                      style={{
                                        width: "35px",
                                        height: "35px",
                                        borderRadius: "0.5rem",
                                        display: "inline-flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        backgroundColor: "var(--bs-secondary-bg)",
                                        color: "var(--app-muted)",
                                        border: "1px solid var(--app-border)",
                                        cursor: "not-allowed",
                                        opacity: 0.6
                                      }}
                                    >
                                      <i className="bi bi-trash3-fill"></i>
                                    </button>
                                  ) : (
                                    <button
                                      type="button"
                                      className="btn-action btn-delete border-0"
                                      onClick={() => handleDelete(doc.id, doc.docName)}
                                      title="ลบ"
                                      style={{
                                        width: "35px",
                                        height: "35px",
                                        borderRadius: "0.5rem",
                                        display: "inline-flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        backgroundColor: "var(--bs-danger-bg-subtle)",
                                        color: "var(--bs-danger-text-emphasis)",
                                        border: "1px solid var(--bs-danger-border-subtle)"
                                      }}
                                    >
                                      <i className="bi bi-trash3-fill"></i>
                                    </button>
                                  )}
                                </div>
                              </td>
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
        </div>
      </div>

      {/* ========================================================= */}
      {/* MODAL 1: เพิ่มข้อมูลงานหนังสือใหม่ (Add Document Modal) 100% PHP   */}
      {/* ========================================================= */}
      {showAddModal && (
        <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)", zIndex: 1050 }}>
          <div className="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <form onSubmit={handleAddSubmit} className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <div className="modal-header border-bottom-0 pt-4 pb-2 px-4 bg-light">
                <div className="bg-primary bg-opacity-10 text-primary p-2 rounded-3 me-3">
                  <i className="bi bi-plus-square fs-4"></i>
                </div>
                <h5 className="modal-title fw-bold" id="addDocumentModalLabel">เพิ่มข้อมูลงานหนังสือใหม่</h5>
                <button type="button" className="btn-close" onClick={() => setShowAddModal(false)}></button>
              </div>

              <div className="modal-body px-4 pb-4" style={{ backgroundColor: "var(--app-muted-bg)" }}>
                {/* ส่วนที่ 1: ข้อมูลการรับเรื่อง */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-4">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-box-arrow-in-right me-2"></i>ส่วนที่ 1: ข้อมูลการรับเรื่อง
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-4">
                      <label className="form-label fw-semibold text-muted small">ปีงบประมาณ</label>
                      <input
                        type="text"
                        className="form-control"
                        value={formData.fiscal_year}
                        onChange={(e) => setFormData({ ...formData, fiscal_year: e.target.value })}
                        placeholder={`เช่น ${defaultFiscalYear}`}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label fw-semibold text-muted small">เลขรับ กรมฯ</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="ระบุเลขรับกรม"
                        value={formData.dpt_receive_number}
                        onChange={(e) => setFormData({ ...formData, dpt_receive_number: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label fw-semibold text-muted small">วันที่รับ กรมฯ</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.dpt_receive_date}
                        onChange={(e) => setFormData({ ...formData, dpt_receive_date: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">เลขรับ กอพ.</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="ระบุเลขรับกอง"
                        value={formData.division_receive_number}
                        onChange={(e) => setFormData({ ...formData, division_receive_number: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">วันที่รับ กอพ.</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.division_receive_date}
                        onChange={(e) => setFormData({ ...formData, division_receive_date: e.target.value })}
                      />
                    </div>
                  </div>
                </div>

                {/* ส่วนที่ 2: ข้อมูลหนังสือและชื่องาน */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-4">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-file-earmark-text me-2"></i>ส่วนที่ 2: ข้อมูลหนังสือและชื่องาน
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">หน่วยงานต้นเรื่อง</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="ชื่อหน่วยงานผู้ขอ"
                        value={formData.host_agency}
                        onChange={(e) => setFormData({ ...formData, host_agency: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">สังกัดของหน่วยงานต้นเรื่อง</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="เช่น กรม..., กระทรวง..."
                        value={formData.host_agency_affiliation}
                        onChange={(e) => setFormData({ ...formData, host_agency_affiliation: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">เลขที่หนังสือหน่วยงานต้นทาง</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="อ้างอิงเลขที่หนังสือ"
                        value={formData.source_doc_number}
                        onChange={(e) => setFormData({ ...formData, source_doc_number: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">
                        ประเภทงาน <span className="text-danger">*</span>
                      </label>
                      <select
                        className="form-select"
                        value={formData.job_type}
                        onChange={(e) => setFormData({ ...formData, job_type: e.target.value })}
                        required
                      >
                        <option value="" disabled>-- เลือกประเภท --</option>
                        <option value="รับเสด็จฯ">👑 รับเสด็จฯ</option>
                        <option value="ขอความอนุเคราะห์">🤝 ขอความอนุเคราะห์</option>
                        <option value="อื่นๆ">📝 อื่นๆ</option>
                      </select>
                      {formData.job_type === "อื่นๆ" && (
                        <input
                          type="text"
                          className="form-control mt-2"
                          placeholder="โปรดระบุประเภทงาน..."
                          value={formData.job_type_other}
                          onChange={(e) => setFormData({ ...formData, job_type_other: e.target.value })}
                          required
                        />
                      )}
                    </div>

                    <div className="col-md-12">
                      <label className="form-label fw-semibold text-muted small">
                        ชื่องาน <span className="text-danger">*</span>
                      </label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="ระบุชื่องาน"
                        value={formData.doc_name}
                        onChange={(e) => setFormData({ ...formData, doc_name: e.target.value })}
                        required
                      />
                    </div>
                    <div className="col-md-12">
                      <label className="form-label fw-semibold text-muted small">รายละเอียด</label>
                      <textarea
                        className="form-control"
                        rows={2}
                        placeholder="รายละเอียดเพิ่มเติม"
                        value={formData.description}
                        onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                      ></textarea>
                    </div>
                  </div>
                </div>

                {/* ส่วนที่ 3: สถานที่จัดงาน */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-4">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-geo-alt me-2"></i>ส่วนที่ 3: สถานที่จัดงาน
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-12">
                      <label className="form-label fw-semibold text-muted small">หน่วยงาน / ชื่อสถานที่จัดงาน</label>
                      <input
                        type="text"
                        className="form-control"
                        placeholder="ระบุสถานที่จัดงาน"
                        value={formData.venue_name}
                        onChange={(e) => setFormData({ ...formData, venue_name: e.target.value })}
                      />
                    </div>

                    <div className="col-md-4">
                      <label className="form-label fw-semibold text-muted small">จังหวัด</label>
                      <select
                        className="form-select"
                        value={formData.venue_province}
                        onChange={(e) =>
                          setFormData({
                            ...formData,
                            venue_province: e.target.value,
                            venue_district: "",
                            venue_subdistrict: "",
                          })
                        }
                      >
                        <option value="" disabled>-- เลือกจังหวัด --</option>
                        {thaiData
                          .slice()
                          .sort((a, b) => a.name_th.localeCompare(b.name_th, "th"))
                          .map((p) => (
                            <option key={p.id} value={p.name_th}>
                              {p.name_th}
                            </option>
                          ))}
                      </select>
                    </div>

                    <div className="col-md-4">
                      <label className="form-label fw-semibold text-muted small">อำเภอ/เขต</label>
                      <select
                        className="form-select"
                        disabled={!formData.venue_province}
                        value={formData.venue_district}
                        onChange={(e) =>
                          setFormData({
                            ...formData,
                            venue_district: e.target.value,
                            venue_subdistrict: "",
                          })
                        }
                      >
                        <option value="" disabled>
                          {formData.venue_province ? "-- เลือกอำเภอ/เขต --" : "-- เลือกจังหวัดก่อน --"}
                        </option>
                        {addDistricts.map((d) => (
                          <option key={d.id} value={d.name_th}>
                            {d.name_th}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div className="col-md-4">
                      <label className="form-label fw-semibold text-muted small">ตำบล/แขวง</label>
                      <select
                        className="form-select"
                        disabled={!formData.venue_district}
                        value={formData.venue_subdistrict}
                        onChange={(e) => setFormData({ ...formData, venue_subdistrict: e.target.value })}
                      >
                        <option value="" disabled>
                          {formData.venue_district ? "-- เลือกตำบล/แขวง --" : "-- เลือกอำเภอก่อน --"}
                        </option>
                        {addSubdistricts.map((s) => (
                          <option key={s.id} value={s.name_th}>
                            {s.name_th}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>
                </div>

                {/* ส่วนที่ 4: กำหนดการและระยะเวลาดำเนินงาน */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-4">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-calendar-event me-2"></i>ส่วนที่ 4: กำหนดการและระยะเวลาดำเนินงาน
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">วันที่จัดงาน (เริ่ม)</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.event_start_date}
                        onChange={(e) => setFormData({ ...formData, event_start_date: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">วันที่จัดงาน (สิ้นสุด)</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.event_end_date}
                        onChange={(e) => setFormData({ ...formData, event_end_date: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">วันที่ประชุมล่วงหน้า</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.pre_meeting_date}
                        onChange={(e) => setFormData({ ...formData, pre_meeting_date: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">วันที่ขอรับอุปกรณ์ยืม</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.equipment_borrow_date}
                        onChange={(e) => setFormData({ ...formData, equipment_borrow_date: e.target.value })}
                      />
                    </div>
                    <hr className="my-3 text-muted" />
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small text-primary">ระยะเวลาทำงาน กอพ. (เริ่ม)</label>
                      <input
                        type="date"
                        className="form-control border-primary"
                        value={formData.dpt_work_start}
                        onChange={(e) => setFormData({ ...formData, dpt_work_start: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small text-primary">ระยะเวลาทำงาน กอพ. (สิ้นสุด)</label>
                      <input
                        type="date"
                        className="form-control border-primary"
                        value={formData.dpt_work_end}
                        onChange={(e) => setFormData({ ...formData, dpt_work_end: e.target.value })}
                      />
                    </div>
                  </div>
                </div>

                {/* ส่วนที่ 5: ผู้ประสานงานและไฟล์แนบ */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-2">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-person-lines-fill me-2"></i>ส่วนที่ 5: ผู้ประสานงานและไฟล์แนบ
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-12">
                      <label className="form-label fw-bold text-primary small mb-2">
                        <i className="bi bi-person-badge-fill me-1"></i> ผู้ประสานงานฝ่ายปฏิบัติการ (กอพ.)
                      </label>
                      <div className="p-3 bg-light rounded-3 border">
                        <span className="text-muted small">- รอหัวหน้าฝ่ายปฏิบัติการมอบหมาย -</span>
                      </div>
                    </div>
                    <div className="col-md-12">
                      <hr className="my-2 opacity-50" />
                      <label className="form-label fw-semibold text-muted small">ไฟล์เอกสารแนบ (PDF)</label>
                      <input
                        type="file"
                        className="form-control"
                        accept=".pdf"
                        onChange={(e) => {
                          const file = e.target.files?.[0];
                          if (file) {
                            setFormData({ ...formData, attachment_file: file.name });
                          }
                        }}
                      />
                    </div>
                    <div className="col-md-12">
                      <label className="form-label fw-semibold text-muted small">หมายเหตุ</label>
                      <textarea
                        className="form-control"
                        rows={2}
                        placeholder="ข้อความหมายเหตุเพิ่มเติม"
                        value={formData.remark}
                        onChange={(e) => setFormData({ ...formData, remark: e.target.value })}
                      ></textarea>
                    </div>
                  </div>
                </div>
              </div>

              <div className="modal-footer border-top-0 px-4 pb-4 pt-3 bg-light">
                <button type="button" className="btn btn-secondary rounded-pill px-4" onClick={() => setShowAddModal(false)}>
                  ยกเลิก
                </button>
                <button type="submit" className="btn btn-primary-gradient px-5 py-2 fw-bold rounded-pill shadow-sm">
                  <i className="bi bi-save2 me-2"></i> บันทึกข้อมูล
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ========================================================= */}
      {/* MODAL 2: ดูรายละเอียดข้อมูลงาน (View Modal) 100% PHP          */}
      {/* ========================================================= */}
      {viewingDoc && (
        <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)", zIndex: 1050 }}>
          <div className="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <div className="modal-header border-bottom-0 pt-4 pb-2 px-4 bg-light">
                <div className="bg-info bg-opacity-10 text-info p-2 rounded-3 me-3">
                  <i className="bi bi-eye-fill fs-4"></i>
                </div>
                <h5 className="modal-title fw-bold">
                  รายละเอียดข้อมูลงาน (รหัส: {viewingDoc.divisionReceiveNumber || viewingDoc.docNumber || viewingDoc.id})
                </h5>
                <button type="button" className="btn-close" onClick={() => setViewingDoc(null)}></button>
              </div>

              <div className="modal-body px-4 pb-4" style={{ backgroundColor: "var(--app-muted-bg)" }}>
                {/* ส่วนที่ 1 */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-4">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-box-arrow-in-right me-2"></i>ส่วนที่ 1: ข้อมูลการรับเรื่อง
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-4">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>ปีงบประมาณ</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{viewingDoc.fiscalYear || "-"}</div>
                    </div>
                    <div className="col-md-4">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>เลขรับ กรมฯ</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{viewingDoc.dptReceiveNumber || "-"}</div>
                    </div>
                    <div className="col-md-4">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>วันที่รับ กรมฯ</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{formatDateTh(viewingDoc.dptReceiveDate)}</div>
                    </div>
                    <div className="col-md-6">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>เลขรับ กอพ.</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{viewingDoc.divisionReceiveNumber || viewingDoc.docNumber || "-"}</div>
                    </div>
                    <div className="col-md-6">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>วันที่รับ กอพ.</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{formatDateTh(viewingDoc.divisionReceiveDate || viewingDoc.receivedDate)}</div>
                    </div>
                  </div>
                </div>

                {/* ส่วนที่ 2 */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-4">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-file-earmark-text me-2"></i>ส่วนที่ 2: ข้อมูลหนังสือและชื่องาน
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-6">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>หน่วยงานต้นเรื่อง</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{viewingDoc.hostAgency || "-"}</div>
                    </div>
                    <div className="col-md-6">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>สังกัดของหน่วยงานต้นเรื่อง</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{viewingDoc.hostAgencyAffiliation || viewingDoc.hostAffiliation || "-"}</div>
                    </div>
                    <div className="col-md-6">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>เลขที่หนังสือหน่วยงานต้นทาง</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{viewingDoc.sourceDocNumber || "-"}</div>
                    </div>
                    <div className="col-md-6">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>ประเภทงาน</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>
                        {viewingDoc.jobType === "อื่นๆ" && viewingDoc.jobTypeOther ? `อื่นๆ: ${viewingDoc.jobTypeOther}` : (viewingDoc.jobType || "-")}
                      </div>
                    </div>
                    <div className="col-md-12">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>ชื่องาน</div>
                      <div className="view-data fw-bold text-dark" style={{ fontSize: "1rem" }}>{viewingDoc.docName || "-"}</div>
                    </div>
                    <div className="col-md-12">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>รายละเอียด</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)", whiteSpace: "pre-line" }}>{viewingDoc.description || "-"}</div>
                    </div>
                  </div>
                </div>

                {/* ส่วนที่ 3 */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-4">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-geo-alt me-2"></i>ส่วนที่ 3: สถานที่จัดงาน
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-12">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>หน่วยงาน / ชื่อสถานที่จัดงาน</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{viewingDoc.venueName || "-"}</div>
                    </div>
                    <div className="col-md-4">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>จังหวัด</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{viewingDoc.venueProvince || "-"}</div>
                    </div>
                    <div className="col-md-4">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>อำเภอ/เขต</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{viewingDoc.venueDistrict || "-"}</div>
                    </div>
                    <div className="col-md-4">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>ตำบล/แขวง</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{viewingDoc.venueSubdistrict || "-"}</div>
                    </div>
                  </div>
                </div>

                {/* ส่วนที่ 4 */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-4">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-calendar-event me-2"></i>ส่วนที่ 4: กำหนดการและระยะเวลาดำเนินงาน
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-6">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>วันที่จัดงาน (เริ่ม)</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{formatDateTh(viewingDoc.eventStartDate || viewingDoc.startDate)}</div>
                    </div>
                    <div className="col-md-6">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>วันที่จัดงาน (สิ้นสุด)</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{formatDateTh(viewingDoc.eventEndDate || viewingDoc.endDate)}</div>
                    </div>
                    <div className="col-md-6">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>วันที่ประชุมล่วงหน้า</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{formatDateTh(viewingDoc.preMeetingDate)}</div>
                    </div>
                    <div className="col-md-6">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>วันที่ขอรับอุปกรณ์ยืม</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{formatDateTh(viewingDoc.equipmentBorrowDate)}</div>
                    </div>
                    <hr className="my-3 text-muted" />
                    <div className="col-md-6">
                      <div className="view-label text-primary" style={{ fontSize: "0.85rem", fontWeight: 600 }}>ระยะเวลาทำงาน กอพ. (เริ่ม)</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{formatDateTh(viewingDoc.dptWorkStart)}</div>
                    </div>
                    <div className="col-md-6">
                      <div className="view-label text-primary" style={{ fontSize: "0.85rem", fontWeight: 600 }}>ระยะเวลาทำงาน กอพ. (สิ้นสุด)</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)" }}>{formatDateTh(viewingDoc.dptWorkEnd)}</div>
                    </div>
                  </div>
                </div>

                {/* ส่วนที่ 5 */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-2">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-person-lines-fill me-2"></i>ส่วนที่ 5: ผู้ประสานงานและไฟล์แนบ
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-12">
                      <div className="view-label text-primary" style={{ fontSize: "0.85rem", fontWeight: 600 }}>
                        <i className="bi bi-person-badge-fill me-1"></i> ผู้ประสานงานฝ่ายปฏิบัติการ (กอพ.) ที่รับผิดชอบงานนี้
                      </div>
                      <div className="p-3 bg-light rounded-3 border mt-2">
                        {viewingDoc.coordinator && viewingDoc.coordinator !== "ยังไม่กำหนด" ? (
                          <ul className="list-unstyled mb-0">
                            <li className="mb-1">
                              <i className="bi bi-person-check-fill text-success me-2"></i>
                              <span className="fw-medium text-dark">{viewingDoc.coordinator}</span>
                              {viewingDoc.isAcknowledged ? (
                                <span className="badge bg-success-subtle text-success ms-2 px-2 py-1" style={{ fontSize: "0.75rem" }}>
                                  <i className="bi bi-check-circle-fill"></i> รับทราบแล้ว
                                </span>
                              ) : (
                                <span className="badge bg-warning-subtle text-dark ms-2 px-2 py-1" style={{ fontSize: "0.75rem" }}>
                                  <i className="bi bi-clock"></i> รอรับทราบ
                                </span>
                              )}
                            </li>
                          </ul>
                        ) : (
                          <span className="text-muted small">- ยังไม่ได้มอบหมายผู้ประสานงาน -</span>
                        )}
                      </div>
                    </div>
                    <div className="col-md-12">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>ไฟล์เอกสารแนบ (PDF)</div>
                      <div className="view-data">
                        {viewingDoc.attachmentFile ? (
                          <span className="badge bg-danger text-decoration-none">
                            <i className="bi bi-file-earmark-pdf me-1"></i> {viewingDoc.attachmentFile}
                          </span>
                        ) : (
                          "-"
                        )}
                      </div>
                    </div>
                    <div className="col-md-12">
                      <div className="view-label" style={{ fontSize: "0.85rem", color: "var(--app-muted)", fontWeight: 600 }}>หมายเหตุ</div>
                      <div className="view-data" style={{ fontSize: "1rem", color: "var(--app-text)", whiteSpace: "pre-line" }}>{viewingDoc.remark || "-"}</div>
                    </div>
                  </div>
                </div>
              </div>

              <div className="modal-footer border-top-0 px-4 pb-4 pt-3 bg-light">
                <button type="button" className="btn btn-secondary rounded-pill px-5" onClick={() => setViewingDoc(null)}>
                  ปิดหน้าต่าง
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================= */}
      {/* MODAL 3: แก้ไขข้อมูลงาน (Edit Modal) 100% PHP               */}
      {/* ========================================================= */}
      {editingDoc && (
        <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)", zIndex: 1050 }}>
          <div className="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <form onSubmit={handleEditSubmit} className="modal-content shadow-lg border-0" style={{ borderRadius: "1.25rem" }}>
              <div className="modal-header border-bottom-0 pt-4 pb-2 px-4 bg-light">
                <div className="bg-warning bg-opacity-10 text-warning p-2 rounded-3 me-3">
                  <i className="bi bi-pencil-square fs-4"></i>
                </div>
                <h5 className="modal-title fw-bold">
                  แก้ไขข้อมูลงาน (รหัส: {editingDoc.division_receive_number || editingDoc.id})
                </h5>
                <button type="button" className="btn-close" onClick={() => setEditingDoc(null)}></button>
              </div>

              <div className="modal-body px-4 pb-4" style={{ backgroundColor: "var(--app-muted-bg)" }}>
                {/* ส่วนที่ 1: ข้อมูลการรับเรื่อง */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-4">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-box-arrow-in-right me-2"></i>ส่วนที่ 1: ข้อมูลการรับเรื่อง
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-4">
                      <label className="form-label fw-semibold text-muted small">ปีงบประมาณ</label>
                      <input
                        type="text"
                        className="form-control"
                        value={editingDoc.fiscal_year || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, fiscal_year: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label fw-semibold text-muted small">เลขรับ กรมฯ</label>
                      <input
                        type="text"
                        className="form-control"
                        value={editingDoc.dpt_receive_number || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, dpt_receive_number: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label fw-semibold text-muted small">วันที่รับ กรมฯ</label>
                      <input
                        type="date"
                        className="form-control"
                        value={editingDoc.dpt_receive_date || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, dpt_receive_date: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">เลขรับ กอพ.</label>
                      <input
                        type="text"
                        className="form-control"
                        value={editingDoc.division_receive_number || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, division_receive_number: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">วันที่รับ กอพ.</label>
                      <input
                        type="date"
                        className="form-control"
                        value={editingDoc.division_receive_date || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, division_receive_date: e.target.value })}
                      />
                    </div>
                  </div>
                </div>

                {/* ส่วนที่ 2: ข้อมูลหนังสือและชื่องาน */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-4">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-file-earmark-text me-2"></i>ส่วนที่ 2: ข้อมูลหนังสือและชื่องาน
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">หน่วยงานต้นเรื่อง</label>
                      <input
                        type="text"
                        className="form-control"
                        value={editingDoc.host_agency || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, host_agency: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">สังกัดของหน่วยงานต้นเรื่อง</label>
                      <input
                        type="text"
                        className="form-control"
                        value={editingDoc.host_agency_affiliation || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, host_agency_affiliation: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">เลขที่หนังสือหน่วยงานต้นทาง</label>
                      <input
                        type="text"
                        className="form-control"
                        value={editingDoc.source_doc_number || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, source_doc_number: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">
                        ประเภทงาน <span className="text-danger">*</span>
                      </label>
                      <select
                        className="form-select"
                        value={editingDoc.job_type}
                        onChange={(e) => setEditingDoc({ ...editingDoc, job_type: e.target.value })}
                        required
                      >
                        <option value="รับเสด็จฯ">👑 รับเสด็จฯ</option>
                        <option value="ขอความอนุเคราะห์">🤝 ขอความอนุเคราะห์</option>
                        <option value="อื่นๆ">📝 อื่นๆ</option>
                      </select>
                      {editingDoc.job_type === "อื่นๆ" && (
                        <input
                          type="text"
                          className="form-control mt-2"
                          value={editingDoc.job_type_other || ""}
                          placeholder="ระบุประเภทงาน..."
                          onChange={(e) => setEditingDoc({ ...editingDoc, job_type_other: e.target.value })}
                        />
                      )}
                    </div>

                    <div className="col-md-12">
                      <label className="form-label fw-semibold text-muted small">
                        ชื่องาน <span className="text-danger">*</span>
                      </label>
                      <input
                        type="text"
                        className="form-control"
                        value={editingDoc.doc_name || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, doc_name: e.target.value })}
                        required
                      />
                    </div>
                    <div className="col-md-12">
                      <label className="form-label fw-semibold text-muted small">รายละเอียด</label>
                      <textarea
                        className="form-control"
                        rows={2}
                        value={editingDoc.description || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, description: e.target.value })}
                      ></textarea>
                    </div>
                  </div>
                </div>

                {/* ส่วนที่ 3: สถานที่จัดงาน (แก้ไข) */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-4">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-geo-alt me-2"></i>ส่วนที่ 3: สถานที่จัดงาน (แก้ไข)
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-12">
                      <label className="form-label fw-semibold text-muted small">หน่วยงาน / ชื่อสถานที่จัดงาน</label>
                      <input
                        type="text"
                        className="form-control"
                        value={editingDoc.venue_name || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, venue_name: e.target.value })}
                      />
                    </div>

                    <div className="col-md-4">
                      <label className="form-label fw-semibold text-muted small">จังหวัด</label>
                      <select
                        className="form-select"
                        value={editingDoc.venue_province || ""}
                        onChange={(e) =>
                          setEditingDoc({
                            ...editingDoc,
                            venue_province: e.target.value,
                            venue_district: "",
                            venue_subdistrict: "",
                          })
                        }
                      >
                        <option value="" disabled>-- เลือกจังหวัด --</option>
                        {thaiData
                          .slice()
                          .sort((a, b) => a.name_th.localeCompare(b.name_th, "th"))
                          .map((p) => (
                            <option key={p.id} value={p.name_th}>
                              {p.name_th}
                            </option>
                          ))}
                      </select>
                    </div>

                    <div className="col-md-4">
                      <label className="form-label fw-semibold text-muted small">อำเภอ/เขต</label>
                      <select
                        className="form-select"
                        disabled={!editingDoc.venue_province}
                        value={editingDoc.venue_district || ""}
                        onChange={(e) =>
                          setEditingDoc({
                            ...editingDoc,
                            venue_district: e.target.value,
                            venue_subdistrict: "",
                          })
                        }
                      >
                        <option value="" disabled>
                          {editingDoc.venue_province ? "-- เลือกอำเภอ/เขต --" : "-- เลือกจังหวัดก่อน --"}
                        </option>
                        {editDistricts.map((d) => (
                          <option key={d.id} value={d.name_th}>
                            {d.name_th}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div className="col-md-4">
                      <label className="form-label fw-semibold text-muted small">ตำบล/แขวง</label>
                      <select
                        className="form-select"
                        disabled={!editingDoc.venue_district}
                        value={editingDoc.venue_subdistrict || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, venue_subdistrict: e.target.value })}
                      >
                        <option value="" disabled>
                          {editingDoc.venue_district ? "-- เลือกตำบล/แขวง --" : "-- เลือกอำเภอก่อน --"}
                        </option>
                        {editSubdistricts.map((s) => (
                          <option key={s.id} value={s.name_th}>
                            {s.name_th}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>
                </div>

                {/* ส่วนที่ 4: กำหนดการและระยะเวลาดำเนินงาน */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-4">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-calendar-event me-2"></i>ส่วนที่ 4: กำหนดการและระยะเวลาดำเนินงาน
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">วันที่จัดงาน (เริ่ม)</label>
                      <input
                        type="date"
                        className="form-control"
                        value={editingDoc.event_start_date || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, event_start_date: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">วันที่จัดงาน (สิ้นสุด)</label>
                      <input
                        type="date"
                        className="form-control"
                        value={editingDoc.event_end_date || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, event_end_date: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">วันที่ประชุมล่วงหน้า</label>
                      <input
                        type="date"
                        className="form-control"
                        value={editingDoc.pre_meeting_date || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, pre_meeting_date: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small">วันที่ขอรับอุปกรณ์ยืม</label>
                      <input
                        type="date"
                        className="form-control"
                        value={editingDoc.equipment_borrow_date || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, equipment_borrow_date: e.target.value })}
                      />
                    </div>
                    <hr className="my-3 text-muted" />
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small text-primary">ระยะเวลาทำงาน กอพ. (เริ่ม)</label>
                      <input
                        type="date"
                        className="form-control border-primary"
                        value={editingDoc.dpt_work_start || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, dpt_work_start: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label fw-semibold text-muted small text-primary">ระยะเวลาทำงาน กอพ. (สิ้นสุด)</label>
                      <input
                        type="date"
                        className="form-control border-primary"
                        value={editingDoc.dpt_work_end || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, dpt_work_end: e.target.value })}
                      />
                    </div>
                  </div>
                </div>

                {/* ส่วนที่ 5: ผู้ประสานงานและไฟล์แนบ */}
                <div className="bg-white p-4 rounded-4 shadow-sm mb-2">
                  <h6 className="fw-bold form-section-title" style={{ fontSize: "1.1rem", color: "var(--app-heading)", borderBottom: "2px solid var(--app-border)", paddingBottom: "0.5rem" }}>
                    <i className="bi bi-person-lines-fill me-2"></i>ส่วนที่ 5: ผู้ประสานงานและไฟล์แนบ
                  </h6>
                  <div className="row g-3">
                    <div className="col-md-12">
                      <label className="form-label fw-bold text-primary small mb-2">
                        <i className="bi bi-person-badge-fill me-1"></i> ผู้ประสานงานฝ่ายปฏิบัติการ (กอพ.) ที่รับผิดชอบงานนี้
                      </label>
                      <div className="p-3 bg-light rounded-3 border">
                        {editingDoc.coordinator && editingDoc.coordinator !== "ยังไม่กำหนด" ? (
                          <ul className="list-unstyled mb-0">
                            <li className="mb-1">
                              <i className="bi bi-person-check-fill text-success me-2"></i>
                              <span className="fw-medium text-dark">{editingDoc.coordinator}</span>
                            </li>
                          </ul>
                        ) : (
                          <span className="text-muted small">- ยังไม่ได้มอบหมายผู้ประสานงาน -</span>
                        )}
                      </div>
                    </div>

                    <div className="col-md-12">
                      <hr className="my-2 opacity-50" />
                      <label className="form-label fw-semibold text-muted small">
                        อัปเดตไฟล์เอกสารใหม่ (PDF) <span className="fw-normal text-muted">*ปล่อยว่างหากใช้ไฟล์เดิม</span>
                      </label>
                      <input
                        type="file"
                        className="form-control"
                        accept=".pdf"
                        onChange={(e) => {
                          const file = e.target.files?.[0];
                          if (file) {
                            setEditingDoc({ ...editingDoc, attachment_file: file.name });
                          }
                        }}
                      />
                      {editingDoc.attachment_file && (
                        <div className="mt-2 small text-success">
                          <i className="bi bi-check-circle-fill me-1"></i> มีไฟล์แนบเดิมอยู่แล้ว:{" "}
                          <span className="fw-bold">{editingDoc.attachment_file}</span>
                        </div>
                      )}
                    </div>
                    <div className="col-md-12">
                      <label className="form-label fw-semibold text-muted small">หมายเหตุ</label>
                      <textarea
                        className="form-control"
                        rows={2}
                        value={editingDoc.remark || ""}
                        onChange={(e) => setEditingDoc({ ...editingDoc, remark: e.target.value })}
                      ></textarea>
                    </div>
                  </div>
                </div>
              </div>

              <div className="modal-footer border-top-0 px-4 pb-4 pt-3 bg-light">
                <button type="button" className="btn btn-secondary rounded-pill px-4" onClick={() => setEditingDoc(null)}>
                  ยกเลิก
                </button>
                <button type="submit" className="btn btn-primary-gradient px-5 py-2 fw-bold rounded-pill shadow-sm">
                  <i className="bi bi-save2 me-2"></i> บันทึก
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
