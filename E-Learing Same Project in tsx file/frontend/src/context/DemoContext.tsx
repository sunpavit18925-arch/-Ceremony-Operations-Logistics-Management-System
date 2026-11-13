"use client";

import React, { createContext, useContext, useState, useEffect, ReactNode } from "react";
import { User, RoleType } from "../types/index";

export interface DemoRoleItem {
  id: RoleType;
  label: string;
  thai: string;
}

export interface DemoContextValue {
  currentUser: User | null;
  currentRole: RoleType | "guest";
  isInitialized: boolean;
  loginAsRole: (roleId: RoleType) => void;
  switchRole: (roleId: RoleType) => void;
  showNotice: (msg: string, type?: "success" | "info" | "warning" | "danger" | "error") => void;
  logout: () => void;
  resetAllData: () => Promise<void>;
  isResetting: boolean;
  effectiveZone: string;
  setEffectiveZone: (zone: string) => void;
  roles: DemoRoleItem[];
  users: User[];
}

const DemoContext = createContext<DemoContextValue | undefined>(undefined);

export const DEFAULT_USERS: User[] = [
  {
    id: 1,
    username: "superadmin_demo",
    fname: "สมชาย",
    lname: "ยอดบริหาร",
    name: "สมชาย ยอดบริหาร",
    departCode: "DPT-001",
    department: "ศูนย์เทคโนโลยีสารสนเทศ",
    email: "somchai.s@demo.gov.th",
    phone: "081-111-2222",
    cid: "1100100000001",
    role: "super_admin",
    status: "active"
  },
  {
    id: 2,
    username: "admin_demo",
    fname: "ประสิทธิ์",
    lname: "จัดการงาน",
    name: "ประสิทธิ์ จัดการงาน",
    departCode: "DPT-002",
    department: "กองกลาง ฝ่ายสารบรรณ",
    email: "prasit.m@demo.gov.th",
    phone: "081-222-3333",
    cid: "1100100000002",
    role: "admin",
    status: "active"
  },
  {
    id: 3,
    username: "head_op_demo",
    fname: "วิโรจน์",
    lname: "นำปฏิบัติ",
    name: "วิโรจน์ นำปฏิบัติ",
    departCode: "DPT-003",
    department: "Ceremony Operations & Production Division",
    email: "wiroj.h@demo.gov.th",
    phone: "081-333-4444",
    cid: "1100100000003",
    role: "head_operation",
    status: "active"
  },
  {
    id: 4,
    username: "coord_op_demo",
    fname: "ณัฐพงษ์",
    lname: "ประสานมิตร",
    name: "ณัฐพงษ์ ประสานมิตร",
    departCode: "DPT-003",
    department: "Ceremony Operations & Production Division",
    email: "nattapong.c@demo.gov.th",
    phone: "081-444-5555",
    cid: "1100100000004",
    role: "coordinator_operation",
    status: "active"
  },
  {
    id: 5,
    username: "staff_op_demo",
    fname: "กิตติ",
    lname: "พร้อมลงพื้นที่",
    name: "กิตติ พร้อมลงพื้นที่",
    departCode: "DPT-003",
    department: "Ceremony Operations & Production Division",
    email: "kitti.s@demo.gov.th",
    phone: "081-555-6666",
    cid: "1100100000005",
    role: "staff_operation",
    status: "active"
  },
  {
    id: 6,
    username: "head_veh_demo",
    fname: "อนันต์",
    lname: "คุมยานยนต์",
    name: "อนันต์ คุมยานยนต์",
    departCode: "DPT-004",
    department: "ฝ่ายยานพาหนะและขนส่ง",
    email: "anan.v@demo.gov.th",
    phone: "081-666-7777",
    cid: "1100100000006",
    role: "head_vehicle",
    status: "active"
  },
  {
    id: 7,
    username: "staff_veh_demo",
    fname: "วิชัย",
    lname: "ขับปลอดภัย",
    name: "วิชัย ขับปลอดภัย",
    departCode: "DPT-004",
    department: "ฝ่ายยานพาหนะและขนส่ง",
    email: "wichai.d@demo.gov.th",
    phone: "081-777-8888",
    cid: "1100100000007",
    role: "staff_vehicle",
    status: "active"
  },
  {
    id: 8,
    username: "pending_demo",
    fname: "มาณี",
    lname: "รออนุมัติ",
    name: "มาณี รออนุมัติ",
    departCode: "DPT-005",
    department: "สำนักผังเมืองรวม",
    email: "manee.p@demo.gov.th",
    phone: "081-888-9999",
    cid: "1100100000008",
    role: "pending",
    status: "pending"
  }
];

export const DEMO_ROLES: DemoRoleItem[] = [
  { id: "super_admin", label: "ผู้ดูแลระบบสูงสุด (Super Admin)", thai: "ผู้ดูแลระบบสูงสุด" },
  { id: "admin", label: "ผู้ดูแลระบบ (Admin)", thai: "ผู้ดูแลระบบ" },
  { id: "head_operation", label: "หัวหน้าฝ่ายปฏิบัติการ", thai: "หัวหน้าฝ่ายปฏิบัติการ" },
  { id: "coordinator_operation", label: "ฝ่ายปฏิบัติการ (ผู้ประสานงาน)", thai: "ฝ่ายปฏิบัติการ" },
  { id: "staff_operation", label: "เจ้าหน้าที่ฝ่ายปฏิบัติการ", thai: "ฝ่ายปฏิบัติการ" },
  { id: "head_vehicle", label: "หัวหน้าฝ่ายยานยนต์", thai: "หัวหน้าฝ่ายยานยนต์" },
  { id: "staff_vehicle", label: "เจ้าหน้าที่ฝ่ายยานยนต์", thai: "ฝ่ายยานยนต์" },
  { id: "pending", label: "รออนุมัติสิทธิ์การใช้งาน", thai: "รออนุมัติ" }
];

export function DemoProvider({ children }: { children: ReactNode }) {
  const [currentUser, setCurrentUser] = useState<User | null>(null);
  const [currentRole, setCurrentRole] = useState<RoleType | "guest">("guest");
  const [isInitialized, setIsInitialized] = useState<boolean>(false);
  const [isResetting, setIsResetting] = useState<boolean>(false);
  const [effectiveZone, setEffectiveZone] = useState<string>("admin");

  useEffect(() => {
    try {
      const savedUser = localStorage.getItem("pabpla_demo_user");
      const savedRole = localStorage.getItem("pabpla_demo_role") as RoleType | null;

      if (savedUser) {
        const parsed = JSON.parse(savedUser) as User;
        setCurrentUser(parsed);
        const resolvedRole = savedRole || parsed.role || "super_admin";
        setCurrentRole(resolvedRole);
        setEffectiveZone(
          resolvedRole === "super_admin" || resolvedRole === "admin"
            ? "admin"
            : resolvedRole === "head_operation"
            ? "head_operation"
            : resolvedRole === "coordinator_operation"
            ? "coordinator_operation"
            : resolvedRole === "staff_operation"
            ? "staff_operation"
            : resolvedRole === "head_vehicle"
            ? "head_vehicle"
            : resolvedRole === "staff_vehicle"
            ? "staff_vehicle"
            : "admin"
        );
      } else {
        // Default to super_admin if not chosen yet
        setCurrentUser(DEFAULT_USERS[0]);
        setCurrentRole("super_admin");
      }
    } catch (e) {
      setCurrentUser(DEFAULT_USERS[0]);
      setCurrentRole("super_admin");
    } finally {
      setIsInitialized(true);
    }
  }, []);

  const loginAsRole = (roleId: RoleType) => {
    const user = DEFAULT_USERS.find((u) => u.role === roleId) || DEFAULT_USERS[0];
    setCurrentUser(user);
    setCurrentRole(roleId);
    setEffectiveZone(
      roleId === "super_admin" || roleId === "admin"
        ? "admin"
        : roleId === "head_operation"
        ? "head_operation"
        : roleId === "coordinator_operation"
        ? "coordinator_operation"
        : roleId === "staff_operation"
        ? "staff_operation"
        : roleId === "head_vehicle"
        ? "head_vehicle"
        : roleId === "staff_vehicle"
        ? "staff_vehicle"
        : "admin"
    );
    try {
      localStorage.setItem("pabpla_demo_user", JSON.stringify(user));
      localStorage.setItem("pabpla_demo_role", roleId);
    } catch (e) {}
  };

  const logout = () => {
    setCurrentUser(null);
    setCurrentRole("guest");
    try {
      localStorage.removeItem("pabpla_demo_user");
      localStorage.removeItem("pabpla_demo_role");
    } catch (e) {}
  };

  const resetAllData = async () => {
    setIsResetting(true);
    try {
      await fetch("http://localhost:4000/api/demo/reset", { method: "POST" });
    } catch (e) {
      console.error(e);
    }
    setIsResetting(false);
    window.dispatchEvent(new Event("demo-data-reset"));
  };

  const switchRole = (roleId: RoleType) => {
    loginAsRole(roleId);
  };

  const showNotice = (msg: string, type: "success" | "info" | "warning" | "danger" | "error" = "info") => {
    console.log(`[Demo Notice - ${type}]: ${msg}`);
  };

  return (
    <DemoContext.Provider
      value={{
        currentUser,
        currentRole,
        isInitialized,
        loginAsRole,
        switchRole,
        showNotice,
        logout,
        resetAllData,
        isResetting,
        effectiveZone,
        setEffectiveZone,
        roles: DEMO_ROLES,
        users: DEFAULT_USERS
      }}
    >
      {children}
    </DemoContext.Provider>
  );
}

export function useDemo(): DemoContextValue {
  const context = useContext(DemoContext);
  if (!context) {
    throw new Error("useDemo must be used within a DemoProvider");
  }
  return context;
}
