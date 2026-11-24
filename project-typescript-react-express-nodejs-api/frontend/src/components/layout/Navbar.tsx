"use client";

import React, { useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useDemo } from "@/context/DemoContext";
import { ComsLogo } from "@/components/branding/SystemBrand";

export default function Navbar() {
  const pathname = usePathname();
  const router = useRouter();
  const { currentUser, currentRole, isInitialized, logout, effectiveZone, setEffectiveZone } = useDemo();
  const [dropdownOpen, setDropdownOpen] = useState<boolean>(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState<boolean>(false);

  // If not hydrated yet, not logged in, or on root login page or welcome page, hide navbar
  if (!isInitialized || !currentUser || pathname === "/" || pathname === "/welcome") {
    return null;
  }

  const isSuperAdmin = currentRole === "super_admin";
  const user_role = currentRole;

  // Effective role determines the menu links
  let activeRole: string = user_role;
  if (isSuperAdmin) {
    if (pathname.includes("admin2") || pathname.includes("admin") || pathname.includes("home_admin")) {
      activeRole = "admin";
    } else if (pathname.includes("head_operation") || pathname.includes("manage_operation2") || pathname.includes("manage_coordinator") || pathname.includes("manage_team") || pathname.includes("view_system_logs")) {
      activeRole = "head_operation";
    } else if (pathname.includes("staff_view_operation2")) {
      activeRole = "coordinator_operation";
    } else if (pathname.includes("staff_view_operation")) {
      activeRole = "staff_operation";
    } else if (pathname.includes("head_vehicle") || pathname.includes("manage_vehicles") || pathname.includes("manage_drivers")) {
      activeRole = "head_vehicle";
    } else if (pathname.includes("home_vehicle")) {
      activeRole = "staff_vehicle";
    } else {
      activeRole = effectiveZone || "admin";
    }
  }

  const roleNameMap: Record<string, string> = {
    super_admin: "ผู้ดูแลระบบสูงสุด",
    admin: "ผู้ดูแลระบบ",
    head_operation: "หัวหน้าฝ่ายปฏิบัติการ",
    coordinator_operation: "ฝ่ายปฏิบัติการ (ผู้ประสานงาน)",
    staff_operation: "ฝ่ายปฏิบัติการ",
    head_vehicle: "หัวหน้าฝ่ายยานยนต์",
    staff_vehicle: "ฝ่ายยานยนต์",
    pending: "รออนุมัติ",
    guest: "ผู้มาเยือน"
  };

  const handleLogout = () => {
    logout();
    router.push("/?logged_out=1");
  };

  const handleZoneSelect = (zone: string, targetUrl: string) => {
    setEffectiveZone(zone);
    setDropdownOpen(false);
    setMobileMenuOpen(false);
    router.push(targetUrl);
  };

  const displayName = currentUser ? `${currentUser.fname || ''} ${currentUser.lname || ''}`.trim() : "";

  const renderZoneSwitcher = (mobile: boolean) => (
    <div className={`dropdown position-relative ${mobile ? "d-xxl-none my-3" : "d-none d-xxl-block"}`}>
      <button
        className="btn btn-sm btn-outline-warning dropdown-toggle d-flex align-items-center"
        type="button"
        aria-expanded={dropdownOpen}
        onClick={() => setDropdownOpen(!dropdownOpen)}
        style={{ borderRadius: "8px", fontSize: "0.82rem", padding: "0.35rem 0.65rem" }}
      >
        <i className="bi bi-grid-fill me-1"></i> โซนระบบ
      </button>
      {dropdownOpen && (
        <ul className="dropdown-menu shadow show position-absolute mt-1" style={{ zIndex: 1050 }}>
          <li><h6 className="dropdown-header text-primary fw-bold"><i className="bi bi-gear-fill me-1"></i> โซนผู้ดูแลระบบ</h6></li>
          <li>
            <button type="button" className={`dropdown-item ps-4 ${pathname.includes('home_admin') ? 'active' : ''}`} onClick={() => handleZoneSelect('admin', '/home_admin')}>
              <i className="bi bi-person-gear me-2"></i>ผู้ดูแลระบบ (Admin)
            </button>
          </li>

          <li><hr className="dropdown-divider" /></li>
          <li><h6 className="dropdown-header text-primary fw-bold"><i className="bi bi-building me-1"></i> โซนฝ่ายปฏิบัติการ</h6></li>
          <li>
            <button type="button" className={`dropdown-item ps-4 ${pathname.includes('home_head_operation') ? 'active' : ''}`} onClick={() => handleZoneSelect('head_operation', '/home_head_operation')}>
              <i className="bi bi-person-badge me-2"></i>หัวหน้าฝ่ายปฏิบัติการ
            </button>
          </li>
          <li>
            <button type="button" className={`dropdown-item ps-4 ${pathname.includes('home_operation') ? 'active' : ''}`} onClick={() => handleZoneSelect('coordinator_operation', '/home_operation')}>
              <i className="bi bi-people me-2"></i>ฝ่ายปฏิบัติการ
            </button>
          </li>

          <li><hr className="dropdown-divider" /></li>
          <li><h6 className="dropdown-header text-primary fw-bold"><i className="bi bi-truck me-1"></i> โซนฝ่ายยานยนต์</h6></li>
          <li>
            <button type="button" className={`dropdown-item ps-4 ${pathname.includes('home_head_vehicle') ? 'active' : ''}`} onClick={() => handleZoneSelect('head_vehicle', '/home_head_vehicle')}>
              <i className="bi bi-person-badge me-2"></i>หัวหน้าฝ่ายยานยนต์
            </button>
          </li>
          <li>
            <button type="button" className={`dropdown-item ps-4 ${pathname.includes('home_vehicle') ? 'active' : ''}`} onClick={() => handleZoneSelect('staff_vehicle', '/home_vehicle')}>
              <i className="bi bi-car-front me-2"></i>ฝ่ายยานยนต์
            </button>
          </li>
        </ul>
      )}
    </div>
  );

  return (
    <nav className="navbar navbar-expand-xxl navbar-dark custom-navbar"
      onKeyDown={(event) => { if (event.key === "Escape") { setMobileMenuOpen(false); setDropdownOpen(false); } }}
      onClick={(event) => { if ((event.target as HTMLElement).closest("a")) { setMobileMenuOpen(false); setDropdownOpen(false); } }}
    >
      <div className="container-fluid px-3">
        <div className="d-flex align-items-center">
          <Link className="navbar-brand fw-semibold d-flex align-items-center me-2 me-xl-3" href="/welcome">
            <ComsLogo size={36} className="me-2" decorative />
            COMS Portal
            {isSuperAdmin && (
              <span className="badge bg-danger ms-2" style={{ fontSize: "0.65rem" }}>
                SUPER ADMIN
              </span>
            )}
          </Link>

          {isSuperAdmin && renderZoneSwitcher(false)}
        </div>

        <button
          className="navbar-toggler border-0 shadow-none"
          type="button"
          onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
          aria-label="Toggle navigation"
          aria-controls="primary-navigation"
          aria-expanded={mobileMenuOpen}
        >
          <span className="navbar-toggler-icon"></span>
        </button>

        <div id="primary-navigation" className={`collapse navbar-collapse ${mobileMenuOpen ? "show" : ""} justify-content-between`}>
          {isSuperAdmin && renderZoneSwitcher(true)}
          <ul className={`navbar-nav ${activeRole === 'admin' ? 'navbar-nav-admin' : 'mx-auto'} mb-2 mb-xxl-0`}>
            {activeRole === 'admin' && (
              <>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname === '/home_admin' ? 'active' : ''}`} href="/home_admin">
                    <i className="bi bi-house-door-fill me-1"></i> หน้าหลัก
                  </Link>
                </li>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname === '/admin' ? 'active' : ''}`} href="/admin">
                    <i className="bi bi-person-check-fill me-1"></i> กำหนดสิทธิ์
                  </Link>
                </li>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname === '/admin2' ? 'active' : ''}`} href="/admin2">
                    <i className="bi bi-file-earmark-arrow-down me-1"></i> รับหนังสือ
                  </Link>
                </li>
              </>
            )}

            {activeRole === 'head_operation' && (
              <>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname === '/home_head_operation' ? 'active' : ''}`} href="/home_head_operation">
                    <i className="bi bi-house-door-fill me-1"></i> หน้าหลัก
                  </Link>
                </li>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname.includes('manage_coordinator') ? 'active' : ''}`} href="/manage_coordinator_operation">
                    <i className="bi bi-shield-lock-fill me-1"></i> กำหนดสิทธิ์ผู้ประสานงาน
                  </Link>
              
                </li>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname.includes('manage_operation2') ? 'active' : ''}`} href="/manage_operation2">
                    <i className="bi bi-person-badge me-1"></i> มอบหมายงานและอุปกรณ์
                  </Link>
                </li>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname.includes('manage_team') ? 'active' : ''}`} href="/manage_team">
                    <i className="bi bi-people me-1"></i> จัดชุดปฏิบัติงาน
                  </Link>
                </li>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname.includes('view_system_logs') ? 'active' : ''}`} href="/view_system_logs">
                    <i className="bi bi-clock-history me-1"></i> ประวัติระบบ
                  </Link>
                </li>
              </>
            )}

            {activeRole === 'coordinator_operation' && (
              <>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname === '/home_operation' ? 'active' : ''}`} href="/home_operation">
                    <i className="bi bi-house-door-fill me-1"></i> หน้าหลัก
                  </Link>
                </li>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname.includes('staff_view_operation2') ? 'active' : ''}`} href="/staff_view_operation2">
                    <i className="bi bi-clipboard-check me-1"></i> งานที่ได้รับมอบหมาย
                  </Link>
                </li>
              </>
            )}

            {activeRole === 'staff_operation' && (
              <>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname === '/staff_view_operation' ? 'active' : ''}`} href="/staff_view_operation">
                    <i className="bi bi-file-earmark-text me-1"></i> หนังสือที่ส่งถึงฉัน
                  </Link>
                </li>
              </>
            )}

            {activeRole === 'head_vehicle' && (
              <>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname === '/home_head_vehicle' ? 'active' : ''}`} href="/home_head_vehicle">
                    <i className="bi bi-house-door-fill me-1"></i> หน้าหลัก
                  </Link>
                </li>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname.includes('select_mission') || pathname.includes('manage_vehicle') ? 'active' : ''}`} href="/select_mission">
                    <i className="bi bi-car-front-fill me-1"></i> บริหารจัดการรถ
                  </Link>
                </li>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname.includes('manage_vehicles') ? 'active' : ''}`} href="/manage_vehicles">
                    <i className="bi bi-truck me-1"></i> ข้อมูลรถ
                  </Link>
                </li>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname.includes('manage_drivers') ? 'active' : ''}`} href="/manage_drivers">
                    <i className="bi bi-person-gear me-1"></i> ข้อมูลคนขับ
                  </Link>
                </li>
              </>
            )}

            {activeRole === 'staff_vehicle' && (
              <>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname === '/home_vehicle' ? 'active' : ''}`} href="/home_vehicle">
                    <i className="bi bi-house-door-fill me-1"></i> หน้าหลัก
                  </Link>
                </li>
                <li className="nav-item mx-1">
                  <Link className={`nav-link ${pathname.includes('select_mission') ? 'active' : ''}`} href="/select_mission">
                    <i className="bi bi-car-front-fill me-1"></i> ภารกิจยานยนต์
                  </Link>
                </li>
              </>
            )}
          </ul>

          <div className="d-flex align-items-center ms-xxl-auto mt-3 mt-xxl-0">
            <div className="text-white text-end me-3 d-none d-xxl-block">
              <div className="fw-semibold small" style={{ lineHeight: 1.2 }}>
                {displayName}
              </div>
              <small className="text-white-50" style={{ fontSize: "0.75rem" }}>
                ({roleNameMap[user_role] || user_role})
              </small>
            </div>
            <button
              onClick={handleLogout}
              className="btn btn-outline-danger btn-sm text-white border-danger d-flex align-items-center"
              style={{ borderRadius: "8px" }}
              title="ออกจากระบบ"
            >
              <i className="bi bi-box-arrow-right me-1"></i> ออกจากระบบ
            </button>
          </div>
        </div>
      </div>
    </nav>
  );
}

