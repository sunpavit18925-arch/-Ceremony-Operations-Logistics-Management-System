# ระบบบริหารจัดการงานพลับพลาพิธี (Pabpla Ceremony Management System)
## Enterprise TypeScript Full Stack Architecture Overview

This document describes the modern full-stack TypeScript architecture of the Pabpla Ceremony Management System Portfolio Demo, migrated from the legacy PHP 8 application into a decoupled, strictly type-safe modern enterprise architecture.

---

### 1. High-Level Architecture Diagram

```
+-----------------------------------------------------------------------------------+
|                            CLIENT BROWSER (HR / Tech Leads)                       |
+-----------------------------------------------------------------------------------+
                                         |
                                         | HTTP / REST (JSON)
                                         v
+-----------------------------------------------------------------------------------+
|               FRONTEND: Next.js 16+ (App Router) + React 19 (.tsx)                |
|   - Strict TypeScript (.tsx) Architecture & Clean Component Hierarchy             |
|   - 100% Identical Legacy UI/UX: Bootstrap 5.3 + Bootstrap Icons + Prompt Font     |
|   - Dynamic Role Switcher (8 Simulated Government Roles via DemoContext.tsx)      |
|   - Interactive Workflow Management & Role Action Centers                         |
|   - Documents, Vehicles, Equipments, Missions, Driver Allocations, Live Audit Logs|
+-----------------------------------------------------------------------------------+
                                         |
                         x-demo-role / REST Endpoints
                                         v
+-----------------------------------------------------------------------------------+
|           BACKEND: Node.js 24+ & Express.js REST API (TypeScript .ts)             |
|   - Modular Folder Structure (controllers, routes, middlewares, store, types)     |
|   - Strict Type Safety with Domain Interfaces (Document, User, Vehicle, etc.)     |
|   - Simulated Role-Based Authorization Guard (checkDemoRole)                      |
|   - Business State Transitions (Created -> Assigned -> Acknowledged -> Fleet)     |
|   - Concurrency & Inventory Logic (Equipment decrement/increment)                 |
|   - Automated TypeScript Test Suite (node:test + node:assert)                     |
+-----------------------------------------------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                 DATA STORE: In-Memory Deep-Clone Snapshot Engine                  |
|   - 100% Synthetic & Fictional Thai Dataset (Zero Private/Sensitive Info)         |
|   - 8 Role Profiles, 4 Initial Missions, 6 Equipment Inventories, 4 Fleet Units   |
|   - Immutable Seed Snapshot with Deep-Clone Isolation                             |
|   - Instant "One-Click Reset Demo Data" restores seed state in 5ms                |
+-----------------------------------------------------------------------------------+
```

---

### 2. Backend Modular Structure

```text
backend/
├── src/
│   ├── controllers/       # demoController.ts (Pure business logic, typed req/res)
│   ├── routes/            # demoRoutes.ts (Declarative express router definition)
│   ├── middlewares/       # auth.ts (RBAC header validation), errorMiddleware.ts
│   ├── store/             # dataStore.ts (Deep clone engine), initialData.ts (Typed seeds)
│   ├── types/             # index.ts (Full TypeScript models & interfaces)
│   ├── app.ts             # Express app setup with CORS, JSON body parser, morgan
│   └── server.ts          # Server listener, banner, graceful shutdown
├── tests/                 # api.test.ts (Automated end-to-end API test suite)
├── tsconfig.json          # ES2022 / NodeNext TypeScript configuration
└── package.json
```

---

### 2. Security & Design Patterns Rebuilt

1. **Decoupled Client & Server**:
   - The frontend communicates with Express.js exclusively via RESTful HTTP calls with clean payload structures.
2. **Simulated Role-Based Access Control (RBAC)**:
   - Authorization headers (`x-demo-role`) are verified by backend middleware before executing business actions.
3. **Privilege Escalation Protection**:
   - Only `super_admin` role can grant administrative permissions to others.
4. **Synthetic Data Guard**:
   - All names, vehicle plates, telephone numbers, and royal ceremonies are fictionalized for safe public showcasing.

