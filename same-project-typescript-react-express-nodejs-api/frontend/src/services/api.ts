import { RoleType } from "../types";

const API_BASE = process.env.NEXT_PUBLIC_API_URL || "http://localhost:4000/api/demo";

export interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  error?: string;
  message?: string;
  [key: string]: any;
}

export async function apiRequest<T = any>(
  endpoint: string,
  options: RequestInit = {},
  demoRole: RoleType | string = "super_admin"
): Promise<ApiResponse<T>> {
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    "x-demo-role": demoRole,
    ...((options.headers as Record<string, string>) || {}),
  };

  try {
    const res = await fetch(`${API_BASE}${endpoint}`, {
      ...options,
      headers,
    });
    const data = await res.json();
    return data;
  } catch (err: any) {
    console.error("API Request Error:", err);
    return {
      success: false,
      error: err?.message || "Unknown error",
      message: "ไม่สามารถเชื่อมต่อ Express.js Backend API ได้ (โปรดตรวจสอบว่ารันพอร์ต 4000 หรือไม่)",
    };
  }
}

export const api = {
  getRoles: (role?: string) => apiRequest("/roles", {}, role),
  getUsers: (role?: string) => apiRequest("/users", {}, role),
  updateUserRole: (id: number | string, newRole: string, role?: string) =>
    apiRequest(`/users/${id}/role`, { method: "PATCH", body: JSON.stringify({ role: newRole }) }, role),
  getDashboard: (role?: string) => apiRequest("/dashboard", {}, role),
  getDocuments: (params: Record<string, string> = {}, role?: string) => {
    const query = new URLSearchParams(params).toString();
    return apiRequest(`/documents${query ? `?${query}` : ""}`, {}, role);
  },
  getDocumentById: (id: number | string, role?: string) => apiRequest(`/documents/${id}`, {}, role),
  createDocument: (docData: any, role?: string) =>
    apiRequest("/documents", { method: "POST", body: JSON.stringify(docData) }, role),
  updateDocument: (id: number | string, docData: any, role?: string) =>
    apiRequest(`/documents/${id}`, { method: "PUT", body: JSON.stringify(docData) }, role),
  deleteDocument: (id: number | string, role?: string) =>
    apiRequest(`/documents/${id}`, { method: "DELETE" }, role),
  assignCoordinator: (id: number | string, assignData: any, role?: string) =>
    apiRequest(`/documents/${id}/assign-coordinator`, { method: "POST", body: JSON.stringify(assignData) }, role),
  acknowledgeDocument: (id: number | string, role?: string) =>
    apiRequest(`/documents/${id}/acknowledge`, { method: "POST" }, role),
  submitMeetingReport: (id: number | string, reportData: any, role?: string) =>
    apiRequest(`/documents/${id}/meeting-report`, { method: "POST", body: JSON.stringify(reportData) }, role),
  completeDocument: (id: number | string, role?: string) =>
    apiRequest(`/documents/${id}/complete`, { method: "POST" }, role),
  getVehicles: (role?: string) => apiRequest("/vehicles", {}, role),
  assignVehicle: (assignData: any, role?: string) =>
    apiRequest("/vehicles/assign", { method: "POST", body: JSON.stringify(assignData) }, role),
  getEquipments: (role?: string) => apiRequest("/equipments", {}, role),
  borrowEquipment: (borrowData: any, role?: string) =>
    apiRequest("/equipments/borrow", { method: "POST", body: JSON.stringify(borrowData) }, role),
  returnEquipment: (id: number | string, role?: string) =>
    apiRequest(`/equipments/borrowings/${id}/return`, { method: "POST" }, role),
  getLogs: (role?: string) => apiRequest("/logs", {}, role),
  resetDemoData: () => apiRequest("/reset", { method: "POST" }),
};

