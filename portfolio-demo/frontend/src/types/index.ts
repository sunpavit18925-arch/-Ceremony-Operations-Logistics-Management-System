export type RoleType =
  | "super_admin"
  | "admin"
  | "head_operation"
  | "coordinator_operation"
  | "staff_operation"
  | "head_vehicle"
  | "staff_vehicle"
  | "pending";

export interface Role {
  id: RoleType;
  name: string;
  department: string;
  description: string;
}

export interface User {
  id: number;
  username: string;
  fname: string;
  lname: string;
  name: string;
  departCode?: string;
  department: string;
  email: string;
  phone: string;
  cid?: string;
  role: RoleType;
  status: "active" | "pending" | "inactive";
}

export interface Document {
  id: number;
  fiscalYear: string;
  docNumber: string;
  sourceDocNumber: string;
  docName: string;
  description: string;
  jobType: string;
  jobTypeOther?: string;
  hostAgency: string;
  hostAffiliation: string;
  dptReceiveNumber?: string;
  dptReceiveDate?: string;
  divisionReceiveNumber?: string;
  divisionReceiveDate?: string;
  eventStartDate?: string;
  eventEndDate?: string;
  preMeetingDate?: string;
  equipmentBorrowDate?: string;
  dptWorkStart?: string;
  dptWorkEnd?: string;
  attachmentFile?: string;
  remark?: string;
  coordinator: string;
  coordinatorPhone: string;
  venueName: string;
  venueProvince: string;
  venueDistrict: string;
  venueSubdistrict: string;
  startDate: string;
  endDate: string;
  receivedDate: string;
  createdBy: string;
  assignedToId: number | null;
  status: "pending" | "assigned" | "in_progress" | "completed" | "rejected";
  isAcknowledged: boolean;
  workflowStep: number;
  vehicleAssigned: boolean;
  equipmentBorrowed: boolean;
  teamSet?: string;
}

export interface Equipment {
  id: number;
  code: string;
  name: string;
  category: string;
  totalQty: number;
  availableQty: number;
  unit: string;
  status: string;
}

export interface Borrowing {
  id: number;
  docId: number;
  equipmentCode: string;
  equipmentName: string;
  quantity: number;
  unit: string;
  borrowDate: string;
  returnDate?: string;
  status: "borrowed" | "returned";
  borrowerName: string;
}

export interface Vehicle {
  id: number;
  vehicleType: string;
  brand: string;
  plateLetters?: string;
  plateNumbers?: string;
  plateProvince?: string;
  licensePlate: string;
  status: "active" | "maintenance" | "in_use";
  currentDriver?: string;
}

export interface VehicleAssignment {
  id: number;
  docId: number;
  docNumber: string;
  docName: string;
  vehicleType: string;
  licensePlate: string;
  driverName: string;
  assistant1?: string;
  assistant2?: string;
  startDate: string;
  endDate: string;
  assignedAt: string;
}

export interface SystemLog {
  id: number;
  action: string;
  detail: string;
  user: string;
  timestamp: string;
}

export interface TeamSet {
  setName: string;
  members: string[];
}

export interface DriverQualification {
  userId: number;
  allowedVehicles: string[];
}

export interface DemoContextType {
  currentUser: User | null;
  currentRole: RoleType;
  availableRoles: Role[];
  loginAsRole: (roleId: RoleType) => void;
  logout: () => void;
  resetAllData: () => Promise<boolean>;
  effectiveZone: string;
  setEffectiveZone: (zone: string) => void;
}

