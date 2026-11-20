import {
  Role,
  User,
  Document,
  Equipment,
  Vehicle,
  VehicleAssignment,
  Borrowing,
  MeetingReport,
  SystemLog,
  TeamSet,
  DriverQualification,
  RoleType
} from "../types/index.js";
import {
  initialRoles,
  initialPermissions,
  initialUsers,
  initialDocuments,
  initialEquipments,
  initialVehicles,
  initialVehicleAssignments,
  initialBorrowings,
  initialMeetingReports,
  initialLogs,
  initialTeamSets,
  initialVehicleTypes,
  initialDriverQualifications
} from "./initialData.js";

// In-Memory Data Store with Deep-Clone Snapshot Reset
export class MockDataStore {
  public static readonly MAX_LOGS = 10_000;
  private sequences: Record<string, number> = {};
  public roles!: Role[];
  public permissions!: Record<RoleType, string[]>;
  public users!: User[];
  public documents!: Document[];
  public equipments!: Equipment[];
  public vehicles!: Vehicle[];
  public vehicleAssignments!: VehicleAssignment[];
  public borrowings!: Borrowing[];
  public meetingReports!: MeetingReport[];
  public logs!: SystemLog[];
  public teamSets!: TeamSet[];
  public vehicleTypes!: string[];
  public driverQualifications!: DriverQualification[];
  public lastReset!: string;

  constructor() {
    this.reset();
  }

  public reset(): void {
    this.roles = JSON.parse(JSON.stringify(initialRoles));
    this.permissions = JSON.parse(JSON.stringify(initialPermissions));
    this.users = JSON.parse(JSON.stringify(initialUsers));
    this.documents = JSON.parse(JSON.stringify(initialDocuments));
    this.equipments = JSON.parse(JSON.stringify(initialEquipments));
    this.vehicles = JSON.parse(JSON.stringify(initialVehicles));
    this.vehicleAssignments = JSON.parse(JSON.stringify(initialVehicleAssignments));
    this.borrowings = JSON.parse(JSON.stringify(initialBorrowings));
    this.meetingReports = JSON.parse(JSON.stringify(initialMeetingReports));
    this.logs = JSON.parse(JSON.stringify(initialLogs));
    this.teamSets = JSON.parse(JSON.stringify(initialTeamSets));
    this.vehicleTypes = JSON.parse(JSON.stringify(initialVehicleTypes));
    this.driverQualifications = JSON.parse(JSON.stringify(initialDriverQualifications));
    this.sequences = {};
    for (const [name, records] of Object.entries({
      documents: this.documents, vehicles: this.vehicles,
      vehicleAssignments: this.vehicleAssignments, borrowings: this.borrowings,
      meetingReports: this.meetingReports, logs: this.logs
    })) {
      this.sequences[name] = records.reduce((max, record) => Math.max(max, record.id), 0);
    }
    this.lastReset = new Date().toISOString();
  }

  public nextId(collection: string): number {
    return this.sequences[collection] = (this.sequences[collection] ?? 0) + 1;
  }

  public addLog(action: string, detail: string, user: string = "System Demo"): SystemLog {
    const newLog: SystemLog = {
      id: this.nextId("logs"),
      action,
      detail,
      user,
      timestamp: new Date().toISOString()
    };
    this.logs.unshift(newLog);
    if (this.logs.length > MockDataStore.MAX_LOGS) this.logs.length = MockDataStore.MAX_LOGS;
    return newLog;
  }
}

export const store = new MockDataStore();

