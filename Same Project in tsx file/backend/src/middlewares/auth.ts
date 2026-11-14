import { Response, NextFunction } from "express";
import { store } from "../store/dataStore.js";
import { DemoRequest, RoleType } from "../types/index.js";

// Role-Based Access Control Simulation Middleware
export const checkDemoRole = (allowedRoles: RoleType[] = []) => {
  return (req: DemoRequest, res: Response, next: NextFunction) => {
    // Read role from header or query string (defaults to super_admin for frictionless demo exploration)
    const requestedRole = (req.headers["x-demo-role"] as RoleType) || (req.query.demo_role as RoleType) || "super_admin";

    // Validate that the role is recognized
    const validRoles: RoleType[] = [
      "super_admin",
      "admin",
      "head_operation",
      "coordinator_operation",
      "staff_operation",
      "head_vehicle",
      "staff_vehicle",
      "pending"
    ];

    if (!validRoles.includes(requestedRole)) {
      return res.status(403).json({
        success: false,
        error: `Invalid or unrecognized demo role: ${requestedRole}`
      });
    }

    // Attach role and simulated user profile to request
    req.demoRole = requestedRole;
    req.currentUser = store.users.find(u => u.role === requestedRole) || store.users[0];

    // If specific roles required, check permission
    if (allowedRoles.length > 0 && !allowedRoles.includes(requestedRole)) {
      return res.status(403).json({
        success: false,
        error: `Permission Denied: บทบาท '${requestedRole}' ไม่มีสิทธิ์ดำเนินการในส่วนนี้ (ต้องเป็น: ${allowedRoles.join(", ")})`,
        requiredRoles: allowedRoles,
        currentRole: requestedRole
      });
    }

    next();
  };
};

