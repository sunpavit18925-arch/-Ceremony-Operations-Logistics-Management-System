import { Response } from "express";
import { store } from "../store/dataStore.js";
import { DemoRequest, Document, Vehicle, VehicleAssignment } from "../types/index.js";

function paginate<T>(items: T[], query: DemoRequest["query"], defaultLimit?: number) {
  // Keep existing document consumers compatible; page/limit opt into bounded responses.
  if (defaultLimit === undefined && query.page === undefined && query.limit === undefined) {
    return { data: items, count: items.length };
  }
  const positiveInt = (value: unknown, fallback: number) => {
    const parsed = Number(value);
    return Number.isSafeInteger(parsed) && parsed > 0 ? parsed : fallback;
  };
  const limit = Math.min(positiveInt(query.limit, defaultLimit ?? 25), 100);
  const total = items.length;
  const totalPages = Math.max(1, Math.ceil(total / limit));
  const page = Math.min(positiveInt(query.page, 1), totalPages);
  return {
    data: items.slice((page - 1) * limit, page * limit), count: total,
    pagination: { page, limit, total, totalPages }
  };
}

export const getRoles = (req: DemoRequest, res: Response): void => {
  res.json({
    success: true,
    data: store.roles,
    permissions: store.permissions
  });
};

export const getUsers = (req: DemoRequest, res: Response): void => {
  res.json({
    success: true,
    data: store.users
  });
};

export const updateUserRole = (req: DemoRequest, res: Response): void => {
  const id = parseInt(String(req.params.id), 10);
  const { role } = req.body;
  const user = store.users.find(u => u.id === id);

  if (!user) {
    res.status(404).json({ success: false, error: "User not found" });
    return;
  }

  // Prevent elevating to super_admin if caller is not super_admin
  if (role === "super_admin" && req.demoRole !== "super_admin") {
    res.status(403).json({
      success: false,
      error: "Privilege Escalation Guard: ไม่สามารถแต่งตั้ง Super Admin ได้ยกเว้น Super Admin เท่านั้น"
    });
    return;
  }

  user.role = role;
  if (role !== "pending") {
    user.status = "active";
  }

  store.addLog("UPDATE_ROLE", `ปรับเปลี่ยนสิทธิ์ผู้ใช้ ${user.name} เป็น ${role}`, req.currentUser?.name);

  res.json({
    success: true,
    message: "อัปเดตสิทธิ์ผู้ใช้งานสำเร็จ",
    data: user
  });
};

export const getDashboardStats = (req: DemoRequest, res: Response): void => {
  const role = req.demoRole;
  const docs = store.documents;
  const vehicles = store.vehicles;
  const equipments = store.equipments;

  const counts: Record<Document["status"], number> = { pending: 0, assigned: 0, in_progress: 0, completed: 0, rejected: 0 };
  for (const doc of docs) counts[doc.status]++;
  const equipmentCounts = equipments.reduce((acc, item) => {
    acc.total += item.totalQty;
    acc.available += item.availableQty;
    return acc;
  }, { total: 0, available: 0 });
  const stats = {
    totalDocs: docs.length,
    pendingDocs: counts.pending,
    assignedDocs: counts.assigned,
    inProgressDocs: counts.in_progress,
    completedDocs: counts.completed,
    totalVehicles: vehicles.length,
    activeVehicles: vehicles.filter(v => v.status === "active").length,
    totalEquipments: equipmentCounts.total,
    availableEquipments: equipmentCounts.available,
    activeBorrowings: store.borrowings.filter(b => b.status === "borrowed").length,
    recentLogs: store.logs.slice(0, 5),
    roleView: role
  };

  res.json({
    success: true,
    data: stats
  });
};

export const getDocuments = (req: DemoRequest, res: Response): void => {
  const { status, search } = req.query as { status?: string; search?: string };
  const role = req.demoRole;
  const s = typeof search === "string" ? search.trim().toLowerCase() : "";
  const scoped = role === "coordinator_operation" || role === "staff_operation";
  const docs = store.documents.filter(d => {
    if (scoped && d.assignedToId !== req.currentUser?.id && d.status !== "assigned" && d.status !== "in_progress") return false;
    if (status && status !== "all" && d.status !== status) return false;
    return !s || [d.docNumber, d.divisionReceiveNumber, d.sourceDocNumber, d.docName,
      d.venueName, d.venueProvince, d.hostAgency].some(value => value?.toLowerCase().includes(s));
  });

  res.json({
    success: true,
    ...paginate(docs, req.query)
  });
};

export const getDocumentById = (req: DemoRequest, res: Response): void => {
  const id = parseInt(String(req.params.id), 10);
  const doc = store.documents.find(d => d.id === id);

  if (!doc) {
    res.status(404).json({ success: false, error: "Document not found" });
    return;
  }

  const relatedVehicles = store.vehicleAssignments.filter(v => v.docId === doc.id);
  const relatedBorrowings = store.borrowings.filter(b => b.docId === doc.id);
  const meetingReport = store.meetingReports.find(m => m.docId === doc.id);

  res.json({
    success: true,
    data: doc,
    vehicleAssignments: relatedVehicles,
    borrowings: relatedBorrowings,
    meetingReport
  });
};

export const createDocument = (req: DemoRequest, res: Response): void => {
  const body = req.body;
  if (!body.docName || !body.jobType) {
    res.status(400).json({ success: false, error: "Missing required fields: docName and jobType" });
    return;
  }

  const currentYear = new Date().getFullYear() + 543;
  const newId = store.nextId("documents");
  const docNumber = body.divisionReceiveNumber || body.docNumber || `มท 0701/${100 + newId}`;

  const newDoc: Document = {
    id: newId,
    fiscalYear: body.fiscalYear || `${currentYear}`,
    docNumber,
    sourceDocNumber: body.sourceDocNumber || `ว ${200 + newId}/${currentYear}`,
    docName: body.docName,
    description: body.description || "",
    jobType: body.jobType,
    jobTypeOther: body.jobTypeOther || "",
    hostAgency: body.hostAgency || "สำนักงานปลัดกระทรวงมหาดไทย",
    hostAffiliation: body.hostAgencyAffiliation || body.hostAffiliation || "กระทรวงมหาดไทย",
    dptReceiveNumber: body.dptReceiveNumber || "",
    dptReceiveDate: body.dptReceiveDate || "",
    divisionReceiveNumber: body.divisionReceiveNumber || docNumber,
    divisionReceiveDate: body.divisionReceiveDate || new Date().toISOString().split("T")[0],
    eventStartDate: body.eventStartDate || body.startDate || "",
    eventEndDate: body.eventEndDate || body.endDate || "",
    preMeetingDate: body.preMeetingDate || "",
    equipmentBorrowDate: body.equipmentBorrowDate || "",
    dptWorkStart: body.dptWorkStart || "",
    dptWorkEnd: body.dptWorkEnd || "",
    attachmentFile: body.attachmentFile || "",
    remark: body.remark || "",
    coordinator: "ยังไม่กำหนด",
    coordinatorPhone: "-",
    venueName: body.venueName || "ศูนย์ราชการ",
    venueProvince: body.venueProvince || "กรุงเทพมหานคร",
    venueDistrict: body.venueDistrict || "",
    venueSubdistrict: body.venueSubdistrict || "",
    startDate: body.eventStartDate || body.startDate || new Date().toISOString().split("T")[0],
    endDate: body.eventEndDate || body.endDate || new Date().toISOString().split("T")[0],
    receivedDate: body.divisionReceiveDate || new Date().toISOString(),
    createdBy: req.currentUser?.name || "ประสิทธิ์ จัดการงาน",
    assignedToId: null,
    status: "pending",
    isAcknowledged: false,
    workflowStep: 1,
    vehicleAssigned: false,
    equipmentBorrowed: false
  };

  store.documents.unshift(newDoc);
  store.addLog("CREATE_DOC", `ลงรับหนังสือราชการใหม่ ${newDoc.docNumber} (${newDoc.docName})`, req.currentUser?.name);

  res.status(201).json({
    success: true,
    message: "ลงรับหนังสือราชการเรียบร้อยแล้ว",
    data: newDoc
  });
};

export const updateDocument = (req: DemoRequest, res: Response): void => {
  const id = parseInt(String(req.params.id), 10);
  const doc = store.documents.find(d => d.id === id);

  if (!doc) {
    res.status(404).json({ success: false, error: "Document not found" });
    return;
  }

  const b = req.body;
  if (b.docName) doc.docName = b.docName;
  if (b.jobType) doc.jobType = b.jobType;
  if (b.jobTypeOther !== undefined) doc.jobTypeOther = b.jobTypeOther;
  if (b.fiscalYear !== undefined) doc.fiscalYear = b.fiscalYear;
  if (b.dptReceiveNumber !== undefined) doc.dptReceiveNumber = b.dptReceiveNumber;
  if (b.dptReceiveDate !== undefined) doc.dptReceiveDate = b.dptReceiveDate;
  if (b.divisionReceiveNumber !== undefined) {
    doc.divisionReceiveNumber = b.divisionReceiveNumber;
    doc.docNumber = b.divisionReceiveNumber;
  }
  if (b.divisionReceiveDate !== undefined) doc.divisionReceiveDate = b.divisionReceiveDate;
  if (b.hostAgency !== undefined) doc.hostAgency = b.hostAgency;
  if (b.hostAgencyAffiliation !== undefined) doc.hostAffiliation = b.hostAgencyAffiliation;
  if (b.hostAffiliation !== undefined) doc.hostAffiliation = b.hostAffiliation;
  if (b.sourceDocNumber !== undefined) doc.sourceDocNumber = b.sourceDocNumber;
  if (b.description !== undefined) doc.description = b.description;
  if (b.venueName !== undefined) doc.venueName = b.venueName;
  if (b.venueProvince !== undefined) doc.venueProvince = b.venueProvince;
  if (b.venueDistrict !== undefined) doc.venueDistrict = b.venueDistrict;
  if (b.venueSubdistrict !== undefined) doc.venueSubdistrict = b.venueSubdistrict;
  if (b.eventStartDate !== undefined) {
    doc.eventStartDate = b.eventStartDate;
    doc.startDate = b.eventStartDate;
  }
  if (b.eventEndDate !== undefined) {
    doc.eventEndDate = b.eventEndDate;
    doc.endDate = b.eventEndDate;
  }
  if (b.preMeetingDate !== undefined) doc.preMeetingDate = b.preMeetingDate;
  if (b.equipmentBorrowDate !== undefined) doc.equipmentBorrowDate = b.equipmentBorrowDate;
  if (b.dptWorkStart !== undefined) doc.dptWorkStart = b.dptWorkStart;
  if (b.dptWorkEnd !== undefined) doc.dptWorkEnd = b.dptWorkEnd;
  if (b.attachmentFile !== undefined) doc.attachmentFile = b.attachmentFile;
  if (b.remark !== undefined) doc.remark = b.remark;

  store.addLog("UPDATE_DOC", `ปรับปรุงข้อมูลหนังสือ ${doc.docNumber}`, req.currentUser?.name);

  res.json({
    success: true,
    message: "อัปเดตข้อมูลเอกสารสำเร็จ",
    data: doc
  });
};

export const deleteDocument = (req: DemoRequest, res: Response): void => {
  const id = parseInt(String(req.params.id), 10);
  const index = store.documents.findIndex(d => d.id === id);

  if (index === -1) {
    res.status(404).json({ success: false, error: "Document not found" });
    return;
  }

  const deleted = store.documents.splice(index, 1)[0];
  store.addLog("DELETE_DOC", `ลบหนังสือ ${deleted.docNumber} ออกจากระบบ`, req.currentUser?.name);

  res.json({
    success: true,
    message: `ลบเอกสาร ${deleted.docNumber} เรียบร้อยแล้ว`
  });
};

export const assignCoordinator = (req: DemoRequest, res: Response): void => {
  const id = parseInt(String(req.params.id), 10);
  const { coordinatorId } = req.body;
  const doc = store.documents.find(d => d.id === id);
  const coord = store.users.find(u => u.id === parseInt(String(coordinatorId), 10));

  if (!doc || !coord) {
    res.status(400).json({ success: false, error: "Invalid document ID or coordinator ID" });
    return;
  }

  doc.assignedToId = coord.id;
  doc.coordinator = coord.name;
  doc.coordinatorPhone = coord.phone;
  doc.status = "assigned";
  doc.workflowStep = 2;
  doc.isAcknowledged = false;

  store.addLog("ASSIGN_COORDINATOR", `มอบหมายผู้ประสานงาน ${coord.name} ประจำภารกิจ ${doc.docNumber}`, req.currentUser?.name);

  res.json({
    success: true,
    message: `มอบหมายผู้ประสานงานให้คุณ ${coord.name} สำเร็จ`,
    data: doc
  });
};

export const acknowledgeDocument = (req: DemoRequest, res: Response): void => {
  const id = parseInt(String(req.params.id), 10);
  const doc = store.documents.find(d => d.id === id);

  if (!doc) {
    res.status(404).json({ success: false, error: "Document not found" });
    return;
  }

  doc.isAcknowledged = true;
  doc.status = "in_progress";
  doc.workflowStep = 3;

  store.addLog("ACKNOWLEDGE_DOC", `ผู้ประสานงาน (${req.currentUser?.name}) ยืนยันรับทราบภารกิจ ${doc.docNumber}`, req.currentUser?.name);

  res.json({
    success: true,
    message: `ยืนยันรับทราบภารกิจ ${doc.docNumber} เรียบร้อยแล้ว`,
    data: doc
  });
};

export const submitMeetingReport = (req: DemoRequest, res: Response): void => {
  const id = parseInt(String(req.params.id), 10);
  const doc = store.documents.find(d => d.id === id);

  if (!doc) {
    res.status(404).json({ success: false, error: "Document not found" });
    return;
  }

  const { topic, summary, meetingDate, location, chairmanName } = req.body;

  const report = {
    id: store.nextId("meetingReports"),
    docId: doc.id,
    meetingDate: meetingDate || new Date().toISOString().split("T")[0],
    meetingTime: "10:00 น.",
    location: location || doc.venueName,
    chairmanName: chairmanName || "หัวหน้าฝ่ายปฏิบัติการ",
    topic: topic || "การเตรียมความพร้อมระบบบริหารจัดการพิธีการและโลจิสติกส์",
    summary: summary || "ที่ประชุมมีมติเห็นชอบผังและกำหนดการปฏิบัติงาน",
    reporterName: req.currentUser?.name || "ณัฐพงษ์ ประสานมิตร",
    createdAt: new Date().toISOString()
  };

  store.meetingReports.push(report);
  store.addLog("SUBMIT_REPORT", `บันทึกรายงานการประชุมสำหรับภารกิจ ${doc.docNumber}`, req.currentUser?.name);

  res.status(201).json({
    success: true,
    message: "บันทึกรายงานการประชุมและผังปฏิบัติการสำเร็จ",
    data: report
  });
};

export const completeDocument = (req: DemoRequest, res: Response): void => {
  const id = parseInt(String(req.params.id), 10);
  const doc = store.documents.find(d => d.id === id);

  if (!doc) {
    res.status(404).json({ success: false, error: "Document not found" });
    return;
  }

  doc.status = "completed";
  doc.workflowStep = 5;

  store.addLog("COMPLETE_DOC", `ปิดภารกิจสมบูรณ์ ${doc.docNumber}`, req.currentUser?.name);

  res.json({
    success: true,
    message: `ปิดภารกิจ ${doc.docNumber} เรียบร้อยแล้ว`,
    data: doc
  });
};

export const getVehicles = (req: DemoRequest, res: Response): void => {
  res.json({
    success: true,
    data: store.vehicles,
    assignments: store.vehicleAssignments
  });
};

export const assignVehicle = (req: DemoRequest, res: Response): void => {
  const { docId, vehicleType, licensePlate, driverName, assistant1, assistant2 } = req.body;
  const doc = store.documents.find(d => d.id === parseInt(docId));

  if (!doc) {
    res.status(400).json({ success: false, error: "Invalid document ID" });
    return;
  }

  const assignment: VehicleAssignment = {
    id: store.nextId("vehicleAssignments"),
    docId: doc.id,
    docNumber: doc.docNumber,
    docName: doc.docName,
    vehicleType,
    licensePlate,
    driverName,
    assistant1: assistant1 || "-",
    assistant2: assistant2 || "-",
    startDate: doc.startDate,
    endDate: doc.endDate,
    assignedAt: new Date().toISOString()
  };

  store.vehicleAssignments.push(assignment);
  doc.vehicleAssigned = true;
  doc.workflowStep = 4;

  store.addLog("ASSIGN_VEHICLE", `จัดสรรรถ ${licensePlate} (${driverName}) ให้แก่ ${doc.docNumber}`, req.currentUser?.name);

  res.status(201).json({
    success: true,
    message: `จัดสรรยานพาหนะ ${licensePlate} เรียบร้อยแล้ว`,
    data: assignment
  });
};

export const getEquipments = (req: DemoRequest, res: Response): void => {
  res.json({
    success: true,
    data: store.equipments,
    borrowings: store.borrowings
  });
};

export const borrowEquipment = (req: DemoRequest, res: Response): void => {
  const { docId, equipmentCode, quantity = 1 } = req.body;
  const doc = store.documents.find(d => d.id === parseInt(docId));
  const equipment = store.equipments.find(e => e.code === equipmentCode);

  if (!doc || !equipment) {
    res.status(400).json({ success: false, error: "Invalid document or equipment code" });
    return;
  }

  if (equipment.availableQty < quantity) {
    res.status(400).json({
      success: false,
      error: `อุปกรณ์ ${equipment.name} คงเหลือไม่เพียงพอ (เหลือ ${equipment.availableQty} ${equipment.unit})`
    });
    return;
  }

  equipment.availableQty -= quantity;

  const borrowing = {
    id: store.nextId("borrowings"),
    docId: doc.id,
    equipmentCode: equipment.code,
    equipmentName: equipment.name,
    quantity,
    unit: equipment.unit,
    borrowDate: new Date().toISOString().split("T")[0],
    returnDate: doc.endDate,
    status: "borrowed" as const,
    borrowerName: req.currentUser?.name || "เจ้าหน้าที่ปฏิบัติการ"
  };

  store.borrowings.unshift(borrowing);
  doc.equipmentBorrowed = true;

  store.addLog("BORROW_EQUIPMENT", `เบิกยืมอุปกรณ์ ${equipment.name} จำนวน ${quantity} ${equipment.unit} สำหรับ ${doc.docNumber}`, req.currentUser?.name);

  res.status(201).json({
    success: true,
    message: `เบิกยืม ${equipment.name} จำนวน ${quantity} ${equipment.unit} สำเร็จ`,
    data: borrowing
  });
};

export const returnEquipment = (req: DemoRequest, res: Response): void => {
  const id = parseInt(String(req.params.id), 10);
  const borrowing = store.borrowings.find(b => b.id === id);

  if (!borrowing || borrowing.status === "returned") {
    res.status(400).json({ success: false, error: "Invalid borrowing record or already returned" });
    return;
  }

  borrowing.status = "returned";
  const equipment = store.equipments.find(e => e.code === borrowing.equipmentCode);
  if (equipment) {
    equipment.availableQty += borrowing.quantity;
  }

  store.addLog("RETURN_EQUIPMENT", `ส่งคืนอุปกรณ์ ${borrowing.equipmentName} เรียบร้อย`, req.currentUser?.name);

  res.json({
    success: true,
    message: `ส่งคืน ${borrowing.equipmentName} เรียบร้อยแล้ว`,
    data: borrowing
  });
};

export const getTeamSets = (req: DemoRequest, res: Response): void => {
  res.json({
    success: true,
    data: store.teamSets
  });
};

export const createTeamSet = (req: DemoRequest, res: Response): void => {
  const { setName } = req.body;
  if (!setName) {
    res.status(400).json({ success: false, error: "กรุณาระบุชื่อชุดปฏิบัติงาน" });
    return;
  }

  const existing = store.teamSets.find(t => t.setName === setName);
  if (existing) {
    res.status(400).json({ success: false, error: "ชื่อชุดปฏิบัติงานนี้มีอยู่แล้ว" });
    return;
  }

  const newSet = { setName, members: [] };
  store.teamSets.push(newSet);
  store.addLog("CREATE_SET", `สร้างชุดปฏิบัติงานใหม่: ${setName}`, req.currentUser?.name);

  res.status(201).json({
    success: true,
    message: `สร้างชุดปฏิบัติงาน ${setName} สำเร็จ`,
    data: newSet
  });
};

export const assignStaffToTeam = (req: DemoRequest, res: Response): void => {
  const { setName, staffName } = req.body;
  const team = store.teamSets.find(t => t.setName === setName);
  if (!team) {
    res.status(404).json({ success: false, error: "ไม่พบชุดปฏิบัติงานนี้" });
    return;
  }

  if (!team.members.includes(staffName)) {
    team.members.push(staffName);
    store.addLog("ASSIGN_STAFF", `เพิ่มบุคลากร ${staffName} เข้าชุด ${setName}`, req.currentUser?.name);
  }

  res.json({
    success: true,
    message: `เพิ่ม ${staffName} เข้าชุด ${setName} สำเร็จ`,
    data: team
  });
};

export const removeStaffFromTeam = (req: DemoRequest, res: Response): void => {
  const { setName, staffName } = req.body;
  const team = store.teamSets.find(t => t.setName === setName);
  if (!team) {
    res.status(404).json({ success: false, error: "ไม่พบชุดปฏิบัติงานนี้" });
    return;
  }

  team.members = team.members.filter(m => m !== staffName);
  store.addLog("REMOVE_STAFF", `ลบบุคลากร ${staffName} ออกจากชุด ${setName}`, req.currentUser?.name);

  res.json({
    success: true,
    message: `ลบ ${staffName} ออกจากชุด ${setName} เรียบร้อย`,
    data: team
  });
};

export const assignTeamToDocument = (req: DemoRequest, res: Response): void => {
  const id = parseInt(String(req.params.id), 10);
  const { teamSetName } = req.body;
  const doc = store.documents.find(d => d.id === id);

  if (!doc) {
    res.status(404).json({ success: false, error: "Document not found" });
    return;
  }

  doc.teamSet = teamSetName;
  store.addLog("ASSIGN_TEAM", `มอบหมายชุดปฏิบัติการ ${teamSetName} ให้แก่ ${doc.docNumber}`, req.currentUser?.name);

  res.json({
    success: true,
    message: `มอบหมายชุด ${teamSetName} สำเร็จ`,
    data: doc
  });
};

export const getVehicleTypes = (req: DemoRequest, res: Response): void => {
  res.json({
    success: true,
    data: store.vehicleTypes
  });
};

export const createVehicleType = (req: DemoRequest, res: Response): void => {
  const { typeName } = req.body;
  if (!typeName || store.vehicleTypes.includes(typeName)) {
    res.status(400).json({ success: false, error: "ประเภทรถไม่ถูกต้องหรือมีอยู่แล้ว" });
    return;
  }

  store.vehicleTypes.push(typeName);
  store.addLog("ADD_VEHICLE_TYPE", `เพิ่มประเภทรถใหม่: ${typeName}`, req.currentUser?.name);

  res.status(201).json({
    success: true,
    message: `เพิ่มประเภทรถ ${typeName} สำเร็จ`,
    data: store.vehicleTypes
  });
};

export const updateVehicleType = (req: DemoRequest, res: Response): void => {
  const { oldTypeName, newTypeName } = req.body;
  const index = store.vehicleTypes.indexOf(oldTypeName);
  if (index === -1) {
    res.status(404).json({ success: false, error: "ไม่พบประเภทรถนี้" });
    return;
  }

  store.vehicleTypes[index] = newTypeName;
  store.vehicles.forEach(v => {
    if (v.vehicleType === oldTypeName) v.vehicleType = newTypeName;
  });

  store.addLog("EDIT_VEHICLE_TYPE", `แก้ไขประเภทรถจาก ${oldTypeName} เป็น ${newTypeName}`, req.currentUser?.name);

  res.json({
    success: true,
    message: "แก้ไขประเภทรถสำเร็จ",
    data: store.vehicleTypes
  });
};

export const deleteVehicleType = (req: DemoRequest, res: Response): void => {
  const typeName = String(req.params.typeName);
  store.vehicleTypes = store.vehicleTypes.filter(t => t !== decodeURIComponent(typeName));
  store.addLog("DELETE_VEHICLE_TYPE", `ลบประเภทรถ: ${decodeURIComponent(typeName)}`, req.currentUser?.name);

  res.json({
    success: true,
    message: "ลบประเภทรถเรียบร้อย",
    data: store.vehicleTypes
  });
};

export const createVehicle = (req: DemoRequest, res: Response): void => {
  const { vehicleType, brand, plateLetters, plateNumbers, plateProvince, currentDriver } = req.body;
  const licensePlate = `${plateLetters} ${plateNumbers} ${plateProvince}`;
  
  const newVehicle: Vehicle = {
    id: store.nextId("vehicles"),
    vehicleType,
    brand,
    plateLetters,
    plateNumbers,
    plateProvince,
    licensePlate,
    status: "active",
    currentDriver: currentDriver || "วิชัย ขับปลอดภัย"
  };

  store.vehicles.push(newVehicle);
  store.addLog("ADD_VEHICLE", `เพิ่มยานพาหนะทะเบียน ${licensePlate} (${vehicleType})`, req.currentUser?.name);

  res.status(201).json({
    success: true,
    message: `เพิ่มรถทะเบียน ${licensePlate} เรียบร้อย`,
    data: newVehicle
  });
};

export const updateVehicle = (req: DemoRequest, res: Response): void => {
  const id = parseInt(String(req.params.id), 10);
  const vehicle = store.vehicles.find(v => v.id === id);
  if (!vehicle) {
    res.status(404).json({ success: false, error: "Vehicle not found" });
    return;
  }

  const { vehicleType, brand, plateLetters, plateNumbers, plateProvince, currentDriver, status } = req.body;
  if (vehicleType) vehicle.vehicleType = vehicleType;
  if (brand) vehicle.brand = brand;
  if (plateLetters) vehicle.plateLetters = plateLetters;
  if (plateNumbers) vehicle.plateNumbers = plateNumbers;
  if (plateProvince) vehicle.plateProvince = plateProvince;
  if (plateLetters && plateNumbers && plateProvince) {
    vehicle.licensePlate = `${plateLetters} ${plateNumbers} ${plateProvince}`;
  }
  if (currentDriver !== undefined) vehicle.currentDriver = currentDriver;
  if (status) vehicle.status = status;

  store.addLog("UPDATE_VEHICLE", `อัปเดตข้อมูลรถ ${vehicle.licensePlate}`, req.currentUser?.name);

  res.json({
    success: true,
    message: "อัปเดตข้อมูลยานพาหนะสำเร็จ",
    data: vehicle
  });
};

export const deleteVehicle = (req: DemoRequest, res: Response): void => {
  const id = parseInt(String(req.params.id), 10);
  const vehicle = store.vehicles.find(v => v.id === id);
  if (!vehicle) {
    res.status(404).json({ success: false, error: "Vehicle not found" });
    return;
  }

  store.vehicles = store.vehicles.filter(v => v.id !== id);
  store.addLog("DELETE_VEHICLE", `ลบยานพาหนะ ${vehicle.licensePlate}`, req.currentUser?.name);

  res.json({
    success: true,
    message: `ลบยานพาหนะ ${vehicle.licensePlate} เรียบร้อย`
  });
};

export const getDriverQualifications = (req: DemoRequest, res: Response): void => {
  res.json({
    success: true,
    data: store.driverQualifications
  });
};

export const saveDriverQualifications = (req: DemoRequest, res: Response): void => {
  const { userId, allowedVehicles } = req.body;
  const parsedUserId = parseInt(userId);
  let qual = store.driverQualifications.find(q => q.userId === parsedUserId);

  if (qual) {
    qual.allowedVehicles = allowedVehicles;
  } else {
    qual = { userId: parsedUserId, allowedVehicles };
    store.driverQualifications.push(qual);
  }

  const user = store.users.find(u => u.id === parsedUserId);
  store.addLog("SAVE_DRIVER_QUAL", `กำหนดคุณสมบัติการขับรถให้: ${user ? user.name : parsedUserId}`, req.currentUser?.name);

  res.json({
    success: true,
    message: "บันทึกข้อมูลคุณสมบัติการขับรถเรียบร้อยแล้ว",
    data: qual
  });
};

export const assignCoordinatorRole = (req: DemoRequest, res: Response): void => {
  const { staffId } = req.body;
  const user = store.users.find(u => u.id === parseInt(staffId));
  if (!user) {
    res.status(404).json({ success: false, error: "User not found" });
    return;
  }

  user.role = "coordinator_operation";
  store.addLog("ASSIGN_COORD", `มอบหมายสิทธิ์ผู้ประสานงานให้: ${user.name}`, req.currentUser?.name);

  res.json({
    success: true,
    message: `มอบหมายสิทธิ์ผู้ประสานงานให้ ${user.name} สำเร็จ`,
    data: user
  });
};

export const revokeCoordinatorRole = (req: DemoRequest, res: Response): void => {
  const { userId } = req.body;
  const user = store.users.find(u => u.id === parseInt(userId));
  if (!user) {
    res.status(404).json({ success: false, error: "User not found" });
    return;
  }

  user.role = "staff_operation";
  store.addLog("REVOKE_COORD", `ปลดสิทธิ์ผู้ประสานงานของ: ${user.name}`, req.currentUser?.name);

  res.json({
    success: true,
    message: `ปลดสิทธิ์ผู้ประสานงานของ ${user.name} เรียบร้อยแล้ว`,
    data: user
  });
};

export const getSystemLogs = (req: DemoRequest, res: Response): void => {
  const search = typeof req.query.search === "string" ? req.query.search.trim().toLowerCase() : "";
  const actions = typeof req.query.actions === "string" ? new Set(req.query.actions.split(",")) : null;
  const logs = search || actions ? store.logs.filter(log =>
    (!actions || actions.has(log.action)) &&
    (!search || [log.action, log.detail, log.user].some(value => value.toLowerCase().includes(search)))
  ) : store.logs;
  res.json({
    success: true,
    ...paginate(logs, req.query, 50)
  });
};

export const resetDemoData = (req: DemoRequest, res: Response): void => {
  store.reset();
  res.json({
    success: true,
    message: "รีเซ็ตข้อมูลจำลองสำหรับ Portfolio Demo กลับสู่สถานะเริ่มต้นเรียบร้อยแล้ว (All Mock Data Restored)",
    resetAt: store.lastReset
  });
};
