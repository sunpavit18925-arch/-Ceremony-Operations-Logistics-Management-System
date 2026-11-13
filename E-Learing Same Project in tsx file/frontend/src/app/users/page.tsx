"use client";

import React, { useState, useEffect } from "react";
import { useDemo } from "@/context/DemoContext";
import { api } from "@/services/api";

export default function UsersPage() {
  const { currentRole, roles, showNotice } = useDemo();
  const [users, setUsers] = useState([]);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);

  const fetchUsers = async () => {
    setLoading(true);
    const res = await api.getUsers(currentRole);
    if (res.success) {
      setUsers(res.data);
    }
    setLoading(false);
  };

  useEffect(() => {
    fetchUsers();
    const handleReset = () => fetchUsers();
    window.addEventListener("demo-data-reset", handleReset);
    return () => window.removeEventListener("demo-data-reset", handleReset);
  }, [currentRole]);

  const handleRoleChange = async (userId, newRole) => {
    const res = await api.updateUserRole(userId, newRole, currentRole);
    if (res.success) {
      showNotice("อัปเดตสิทธิ์สำเร็จ", "success");
      fetchUsers();
    } else {
      showNotice(res.error || res.message, "error");
    }
  };

  const getRoleThai = (role) => {
    switch (role) {
      case "super_admin": return "ผู้ดูแลระบบสูงสุด (Super Admin)";
      case "admin": return "ผู้ดูแลระบบ (Admin)";
      case "head_operation": return "หัวหน้าฝ่ายปฏิบัติงาน";
      case "coordinator_operation": return "ผู้ประสานงาน";
      case "staff_operation": return "ฝ่ายปฏิบัติงาน";
      case "head_vehicle": return "หัวหน้าฝ่ายยานยนต์";
      case "staff_vehicle": return "ฝ่ายยานยนต์";
      case "pending": return "รออนุมัติ";
      default: return role;
    }
  };

  const getRoleBadge = (role) => {
    switch (role) {
      case "super_admin": return "bg-danger";
      case "admin": return "bg-primary";
      case "head_operation": return "bg-success";
      case "coordinator_operation": return "bg-info text-dark";
      case "staff_operation": return "bg-secondary";
      case "head_vehicle": return "bg-warning text-dark";
      case "staff_vehicle": return "bg-dark";
      case "pending": return "bg-danger-subtle text-danger border border-danger-subtle";
      default: return "bg-light text-dark";
    }
  };

  const filteredUsers = users.filter((u) => {
    const q = search.toLowerCase();
    return (
      u.name.toLowerCase().includes(q) ||
      u.username.toLowerCase().includes(q) ||
      (u.department && u.department.toLowerCase().includes(q))
    );
  });

  const canEdit = currentRole === "super_admin" || currentRole === "admin";

  return (
    <div className="container py-4">
      {/* Header bar matching PHP admin.php */}
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
          <h4 className="fw-bold text-dark mb-1 d-flex align-items-center">
            <i className="bi bi-person-gear text-primary me-2"></i>
            กำหนดสิทธิ์ผู้ใช้งานในระบบ
          </h4>
          <span className="text-muted small">
            จัดการสิทธิ์การเข้าถึง กำหนดบทบาท และอนุมัติสิทธิ์ผู้ใช้งาน
          </span>
        </div>
        <div className="d-flex gap-2">
          <input
            type="text"
            className="form-control form-control-sm"
            placeholder="ค้นหาชื่อหรือชื่อผู้ใช้..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            style={{ width: "250px" }}
          />
        </div>
      </div>

      {!canEdit && (
        <div className="alert alert-warning py-2 small mb-4" role="alert">
          <i className="bi bi-exclamation-triangle-fill me-2"></i>
          คุณกำลังอยู่ในบทบาท <strong>{currentRole}</strong> (โหมดดูข้อมูลเท่านั้น - การแก้ไขสิทธิ์ต้องใช้สิทธิ์ Admin หรือ Super Admin)
        </div>
      )}

      {/* Card Table matching PHP admin.php */}
      <div className="card custom-card">
        <div className="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
          <h6 className="m-0 fw-bold text-dark">
            <i className="bi bi-people-fill text-primary me-2"></i>
            รายชื่อผู้ใช้งานทั้งหมด ({filteredUsers.length} คน)
          </h6>
        </div>
        <div className="table-responsive">
          <table className="table table-hover table-custom align-middle mb-0">
            <thead>
              <tr>
                <th style={{ width: "80px" }} className="text-center">ลำดับ</th>
                <th>ชื่อ - นามสกุล</th>
                <th>ฝ่าย / สังกัด</th>
                <th className="text-center">บทบาทปัจจุบัน</th>
                <th className="text-center" style={{ width: "240px" }}>ปรับเปลี่ยนบทบาท</th>
              </tr>
            </thead>
            <tbody>
              {filteredUsers.map((u, idx) => (
                <tr key={u.id}>
                  <td className="text-center text-muted">{idx + 1}</td>
                  <td>
                    <div className="fw-semibold text-dark">{u.name}</div>
                    <small className="text-muted">@{u.username} | {u.email}</small>
                  </td>
                  <td>
                    <span className="text-secondary">{u.department}</span>
                    <br />
                    <small className="text-muted font-monospace">{u.departCode}</small>
                  </td>
                  <td className="text-center">
                    <span className={`badge ${getRoleBadge(u.role)} px-2.5 py-1.5 rounded-pill`}>
                      {getRoleThai(u.role)}
                    </span>
                  </td>
                  <td className="text-center">
                    {canEdit ? (
                      <select
                        value={u.role}
                        onChange={(e) => handleRoleChange(u.id, e.target.value)}
                        disabled={u.role === "super_admin" && currentRole !== "super_admin"}
                        className="form-select form-select-sm"
                        style={{ fontSize: "0.85rem" }}
                      >
                        <option value="head_operation">หัวหน้าฝ่ายปฏิบัติงาน</option>
                        <option value="coordinator_operation">ผู้ประสานงาน</option>
                        <option value="staff_operation">ฝ่ายปฏิบัติงาน</option>
                        <option value="head_vehicle">หัวหน้าฝ่ายยานยนต์</option>
                        <option value="staff_vehicle">ฝ่ายยานยนต์</option>
                        <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                        {currentRole === "super_admin" && (
                          <option value="super_admin">ผู้ดูแลระบบสูงสุด (Super Admin)</option>
                        )}
                        <option value="pending">รออนุมัติ</option>
                      </select>
                    ) : (
                      <span className="text-muted small">ไม่มีสิทธิ์แก้ไข</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
