"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { useDemo } from "@/context/DemoContext";
import { api } from "@/services/api";

export default function AdminPage() {
  const { currentRole, isInitialized } = useDemo();
  const [users, setUsers] = useState([]);
  const [search, setSearch] = useState("");
  const [successMsg, setSuccessMsg] = useState("");
  const [errorMsg, setErrorMsg] = useState("");
  const [historyModalUser, setHistoryModalUser] = useState(null);

  // Search supplies the initial expansion; manual toggles override it until the query changes.
  const [sectionOverrides, setSectionOverrides] = useState<Partial<Record<"op" | "veh" | "adm" | "pen", boolean>>>({});

  const fetchUsers = useCallback(() => {
    const load = async () => {
      const res = await api.getUsers(currentRole);
      return { res };
    };
    return load().then(({ res }) => {
      if (res.success) {
        setUsers(res.data);
      }
    }).catch((err) => {
      console.error(err);
    });
  }, [currentRole]);

  useEffect(() => {
    if (!isInitialized) return;
    fetchUsers();
    const handleReset = () => fetchUsers();
    window.addEventListener("demo-data-reset", handleReset);
    return () => window.removeEventListener("demo-data-reset", handleReset);
  }, [fetchUsers, isInitialized]);

  const handleRoleChange = async (userId, targetUser, newRole) => {
    if (!window.confirm(`คุณแน่ใจหรือไม่ว่าต้องการเปลี่ยนสิทธิ์ของ "${targetUser.name}" เป็น "${getRoleThai(newRole)}"?`)) {
      return;
    }
    const res = await api.updateUserRole(userId, newRole, currentRole);
    if (res.success) {
      setSuccessMsg("บันทึกการเปลี่ยนแปลงสิทธิ์เรียบร้อยแล้ว");
      setErrorMsg("");
      fetchUsers();
      setTimeout(() => setSuccessMsg(""), 3000);
    } else {
      setErrorMsg(res.error || res.message || "เกิดข้อผิดพลาดในการบันทึกข้อมูล");
      setTimeout(() => setErrorMsg(""), 4000);
    }
  };

  const getRoleBadge = (role) => {
    switch (role) {
      case "super_admin": return "bg-danger text-white";
      case "staff_operation": return "bg-primary text-white";
      case "coordinator_operation": return "bg-info text-dark";
      case "staff_vehicle": return "bg-success text-white";
      case "head_operation": return "bg-warning text-dark";
      case "head_vehicle": return "bg-danger text-white";
      case "admin": return "bg-dark text-white";
      case "pending": return "bg-secondary text-white";
      default: return "bg-info text-dark";
    }
  };

  const getRoleThai = (role) => {
    switch (role) {
      case "super_admin": return "ผู้ดูแลระบบสูงสุด (Super Admin)";
      case "staff_operation": return "ฝ่ายปฏิบัติงาน";
      case "coordinator_operation": return "ผู้ประสานงาน";
      case "staff_vehicle": return "ฝ่ายยานยนต์";
      case "head_operation": return "หัวหน้าฝ่ายปฏิบัติงาน";
      case "head_vehicle": return "หัวหน้าฝ่ายยานยนต์";
      case "admin": return "ผู้ดูแลระบบ (Admin)";
      case "pending": return "รออนุมัติ";
      default: return role;
    }
  };

  const isSuperAdmin = currentRole === "super_admin";

  // Grouping users
  const query = search.trim().toLowerCase();
  const filtered = users.filter(u => !query ||
    [u.name, `${u.fname || ""} ${u.lname || ""}`, u.username, u.email, u.department, u.role, getRoleThai(u.role)]
      .some(value => value?.toLowerCase().includes(query))
  );

  const group_admin = filtered.filter(u => u.role === "admin" || u.role === "super_admin");
  const group_op = filtered.filter(u => ["head_operation", "staff_operation", "coordinator_operation"].includes(u.role));
  const group_veh = filtered.filter(u => ["head_vehicle", "staff_vehicle"].includes(u.role));
  const group_pending = filtered.filter(u => u.role === "pending");

  const openSections = {
    op: !query || group_op.length > 0,
    veh: !query || group_veh.length > 0,
    adm: !query || group_admin.length > 0,
    pen: !query || group_pending.length > 0,
    ...sectionOverrides
  };
  const toggleSection = (key: keyof typeof openSections) => {
    setSectionOverrides(previous => ({ ...previous, [key]: !openSections[key] }));
  };

  const renderTable = (userList) => {
    if (userList.length === 0) {
      return (
        <div className="text-center py-5 text-muted">
          <i className="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
          {query ? "ไม่พบผู้ใช้งานที่ตรงกับคำค้นหาในกลุ่มนี้" : "ยังไม่มีข้อมูลผู้ใช้งานในกลุ่มนี้"}
        </div>
      );
    }

    return (
      <div className="table-responsive">
        <table className="table table-hover mb-0">
          <thead>
            <tr>
              <th className="ps-4">ข้อมูลผู้ใช้งาน</th>
              <th className="text-center">สถานะปัจจุบัน</th>
              <th className="text-center" style={{ width: "250px" }}>จัดการสิทธิ์ (Role)</th>
              <th className="text-center pe-4" style={{ width: "150px" }}>ประวัติการแก้ไข</th>
            </tr>
          </thead>
          <tbody>
            {userList.map(row => {
              const rowName = row.name || `${row.fname} ${row.lname}`;
              const canEditTarget = isSuperAdmin || row.role !== "super_admin";

              return (
                <tr key={row.id}>
                  <td className="ps-4">
                    <div className="fw-bold text-dark">{rowName}</div>
                    <small className="text-muted">
                      <i className="bi bi-person me-1"></i> {row.username || '-'} | <i className="bi bi-envelope me-1"></i> {row.email || '-'}
                    </small>
                    <div className="small text-secondary mt-1">
                      <i className="bi bi-building me-1"></i> {row.department || '-'}
                    </div>
                    {row.role === "coordinator_operation" && (
                      <div className="mt-1">
                        <span className="badge bg-info text-dark bg-opacity-25 border border-info rounded-pill" style={{ fontSize: "0.75rem" }}>
                          <i className="bi bi-person-lines-fill me-1"></i> คนในฝ่ายปฏิบัติงาน (เป็นผู้ประสานงาน)
                        </span>
                      </div>
                    )}
                  </td>
                  <td className="text-center">
                    <span className={`badge ${getRoleBadge(row.role)} custom-badge`} style={{ borderRadius: "50rem", padding: "0.4em 1em", fontWeight: 400, fontSize: "0.85rem" }}>
                      {getRoleThai(row.role)}
                    </span>
                  </td>
                  <td className="text-center">
                    {!canEditTarget ? (
                      <span className="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                        <i className="bi bi-shield-lock-fill me-1"></i> สิทธิ์สูงสุด (แก้ไขไม่ได้)
                      </span>
                    ) : (
                      <select
                        name="new_role"
                        className="form-select role-select"
                        style={{
                          borderRadius: "6px",
                          border: "1px solid var(--app-border)",
                          fontSize: "0.88rem",
                          padding: "0.35rem 1.75rem 0.35rem 0.75rem",
                          color: "var(--app-text)",
                          textAlign: "center",
                          textAlignLast: "center",
                          minWidth: "220px"
                        }}
                        value={row.role}
                        onChange={(e) => handleRoleChange(row.id, row, e.target.value)}
                      >
                        <option value="head_operation">หัวหน้าฝ่ายปฏิบัติงาน</option>
                        <option value="staff_operation">ฝ่ายปฏิบัติงาน</option>
                        <option value="head_vehicle">หัวหน้าฝ่ายยานยนต์</option>
                        <option value="staff_vehicle">ฝ่ายยานยนต์</option>
                        <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                        <option value="pending">รออนุมัติ</option>
                        {isSuperAdmin && (
                          <option value="super_admin">ผู้ดูแลระบบสูงสุด (Super Admin)</option>
                        )}
                        {row.role === "coordinator_operation" && (
                          <option value="coordinator_operation">ผู้ประสานงาน</option>
                        )}
                      </select>
                    )}
                  </td>
                  <td className="text-center pe-4">
                    <button
                      className="btn btn-sm btn-outline-secondary rounded-pill px-3"
                      onClick={() => setHistoryModalUser(row)}
                    >
                      <i className="bi bi-clock-history"></i> ประวัติ
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    );
  };

  return (
    <div>
      {/* Top Banner identical to PHP admin.php */}
      <div
        className="top-banner"
        style={{
          backgroundColor: "#1e3a8a",
          padding: "2rem 0 5rem 0",
          position: "relative"
        }}
      >
        <div className="container" style={{ maxWidth: "1050px" }}>
          <div className="row align-items-center">
            <div className="col-12 col-md-3 text-start">
              <Link
                href="/home_admin"
                className="btn btn-back text-decoration-none"
                style={{
                  backgroundColor: "var(--app-surface)",
                  color: "var(--app-heading)",
                  border: "none",
                  borderRadius: "6px",
                  fontWeight: 500,
                  fontSize: "0.9rem",
                  padding: "0.4rem 1rem",
                  boxShadow: "0 2px 4px rgba(0,0,0,0.1)"
                }}
              >
                <i className="bi bi-arrow-left"></i> ย้อนกลับ
              </Link>
            </div>
            <div className="col-12 col-md-6 text-center">
              <h1
                className="page-title-center"
                style={{
                  color: "white",
                  fontWeight: 600,
                  fontSize: "26px",
                  margin: 0,
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "10px"
                }}
              >
                <i className="bi bi-person-lines-fill"></i> จัดการและกำหนดสิทธิ์ผู้ใช้งาน
              </h1>
            </div>
            <div className="col-12 col-md-3"></div>
          </div>
        </div>
      </div>

      {/* Main Content overlapping top banner by -3rem */}
      <div
        className="container main-content"
        style={{
          marginTop: "-3rem",
          marginBottom: "3rem",
          position: "relative",
          zIndex: 10
        }}
      >
        {successMsg && (
          <div className="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-3" role="alert">
            <i className="bi bi-check-circle-fill me-2"></i> {successMsg}
            <button type="button" className="btn-close" onClick={() => setSuccessMsg("")}></button>
          </div>
        )}

        {errorMsg && (
          <div className="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-3" role="alert">
            <i className="bi bi-exclamation-triangle-fill me-2"></i> {errorMsg}
            <button type="button" className="btn-close" onClick={() => setErrorMsg("")}></button>
          </div>
        )}

        <div
          className="admin-card"
          style={{
            background: "var(--app-surface)",
            borderRadius: "12px",
            border: "none",
            boxShadow: "0 4px 15px rgba(0,0,0,0.05)",
            maxWidth: "1050px",
            margin: "0 auto"
          }}
        >
          <div
            className="card-header-custom flex-column flex-md-row gap-3"
            style={{
              padding: "1.2rem 1.5rem",
              borderBottom: "1px solid var(--app-border)",
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between"
            }}
          >
            <div className="d-flex align-items-center gap-2">
              <h5
                className="header-title mb-0"
                style={{
                  color: "var(--app-heading)",
                  fontWeight: 600,
                  fontSize: "1.1rem",
                  margin: 0,
                  display: "flex",
                  alignItems: "center",
                  gap: "8px"
                }}
              >
                <i className="bi bi-list-ul"></i> รายชื่อผู้ใช้งานในระบบ
              </h5>
              <div className="badge bg-light text-dark border">
                ทั้งหมด {filtered.length} รายการ
              </div>
            </div>

            <div className="position-relative w-100" style={{ maxWidth: "300px" }}>
              <span className="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                <i className="bi bi-search"></i>
              </span>
              <input
                type="text"
                id="searchUser"
                className="form-control bg-light border-0 shadow-sm ps-5 py-2"
                style={{ borderRadius: "20px" }}
                placeholder="ค้นหาชื่อ, บทบาท..."
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setSectionOverrides({});
                }}
              />
            </div>
          </div>

          <div className="card-body p-4">
            <div className="accordion" id="userRoleAccordion">
              {/* 1. ฝ่ายปฏิบัติงาน */}
              <div className="accordion-item mb-2" style={{ border: "1px solid var(--app-border)", borderRadius: "8px", overflow: "hidden" }}>
                <h2 className="accordion-header">
                  <button
                    className={`accordion-button ${openSections.op ? '' : 'collapsed'}`}
                    type="button"
                    onClick={() => toggleSection('op')}
                    aria-expanded={openSections.op}
                    aria-controls="adminOperationUsers"
                    style={{
                      backgroundColor: openSections.op ? "var(--app-muted-bg)" : "transparent",
                      color: "var(--app-heading)",
                      fontWeight: 600
                    }}
                  >
                    <i className="bi bi-people me-2"></i> ฝ่ายปฏิบัติงาน ({group_op.length} คน)
                  </button>
                </h2>
                {openSections.op && (
                  <div id="adminOperationUsers" className="accordion-collapse collapse show">
                    <div className="accordion-body p-0">
                      {renderTable(group_op)}
                    </div>
                  </div>
                )}
              </div>

              {/* 2. ฝ่ายยานยนต์ */}
              <div className="accordion-item mb-2" style={{ border: "1px solid var(--app-border)", borderRadius: "8px", overflow: "hidden" }}>
                <h2 className="accordion-header">
                  <button
                    className={`accordion-button ${openSections.veh ? '' : 'collapsed'}`}
                    type="button"
                    onClick={() => toggleSection('veh')}
                    aria-expanded={openSections.veh}
                    aria-controls="adminVehicleUsers"
                    style={{
                      backgroundColor: openSections.veh ? "var(--app-muted-bg)" : "transparent",
                      color: "var(--app-heading)",
                      fontWeight: 600
                    }}
                  >
                    <i className="bi bi-truck me-2"></i> ฝ่ายยานยนต์ ({group_veh.length} คน)
                  </button>
                </h2>
                {openSections.veh && (
                  <div id="adminVehicleUsers" className="accordion-collapse collapse show">
                    <div className="accordion-body p-0">
                      {renderTable(group_veh)}
                    </div>
                  </div>
                )}
              </div>

              {/* 3. ผู้ดูแลระบบ */}
              <div className="accordion-item mb-2" style={{ border: "1px solid var(--app-border)", borderRadius: "8px", overflow: "hidden" }}>
                <h2 className="accordion-header">
                  <button
                    className={`accordion-button ${openSections.adm ? '' : 'collapsed'}`}
                    type="button"
                    onClick={() => toggleSection('adm')}
                    aria-expanded={openSections.adm}
                    aria-controls="adminAdministratorUsers"
                    style={{
                      backgroundColor: openSections.adm ? "var(--app-muted-bg)" : "transparent",
                      color: "var(--app-heading)",
                      fontWeight: 600
                    }}
                  >
                    <i className="bi bi-shield-lock me-2"></i> ผู้ดูแลระบบ ({group_admin.length} คน)
                  </button>
                </h2>
                {openSections.adm && (
                  <div id="adminAdministratorUsers" className="accordion-collapse collapse show">
                    <div className="accordion-body p-0">
                      {renderTable(group_admin)}
                    </div>
                  </div>
                )}
              </div>

              {/* 4. ผู้ใช้งานรออนุมัติ */}
              <div className="accordion-item mb-2" style={{ border: "1px solid var(--app-border)", borderRadius: "8px", overflow: "hidden" }}>
                <h2 className="accordion-header">
                  <button
                    className={`accordion-button ${openSections.pen ? '' : 'collapsed'}`}
                    type="button"
                    onClick={() => toggleSection('pen')}
                    aria-expanded={openSections.pen}
                    aria-controls="adminPendingUsers"
                    style={{
                      backgroundColor: openSections.pen ? "var(--app-muted-bg)" : "transparent",
                      color: "var(--app-heading)",
                      fontWeight: 600
                    }}
                  >
                    <i className="bi bi-hourglass-split me-2"></i> ผู้ใช้งานรออนุมัติ ({group_pending.length} คน)
                  </button>
                </h2>
                {openSections.pen && (
                  <div id="adminPendingUsers" className="accordion-collapse collapse show">
                    <div className="accordion-body p-0">
                      {renderTable(group_pending)}
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* History Modal */}
      {historyModalUser && (
        <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: "rgba(0,0,0,0.5)" }}>
          <div className="modal-dialog">
            <div className="modal-content rounded-3 shadow">
              <div className="modal-header bg-light">
                <h6 className="modal-title fw-bold text-dark">
                  <i className="bi bi-clock-history me-1 text-primary"></i> ประวัติการปรับเปลี่ยนสิทธิ์: {historyModalUser.name}
                </h6>
                <button type="button" className="btn-close" onClick={() => setHistoryModalUser(null)}></button>
              </div>
              <div className="modal-body p-3">
                <div className="small text-muted mb-2">
                  บัญชีผู้ใช้: <strong>{historyModalUser.username}</strong> | บทบาทปัจจุบัน: <strong>{getRoleThai(historyModalUser.role)}</strong>
                </div>
                <div className="p-3 bg-light rounded text-center small text-secondary">
                  บันทึกล่าสุด: ปรับเปลี่ยนสิทธิ์โดย ผู้ดูแลระบบ ผ่านระบบบริหารจัดการ
                </div>
              </div>
              <div className="modal-footer bg-light py-2">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setHistoryModalUser(null)}>ปิด</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}


