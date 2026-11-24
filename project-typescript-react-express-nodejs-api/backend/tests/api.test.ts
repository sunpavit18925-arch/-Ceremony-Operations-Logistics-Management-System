import { test, describe, before, after, beforeEach } from "node:test";
import assert from "node:assert";
import { Server } from "node:http";
import { createApp } from "../src/app.js";
import { store, MockDataStore } from "../src/store/dataStore.js";

const app = createApp();
let server: Server;
const PORT = 4099;
const BASE_URL = `http://localhost:${PORT}`;

before(async () => {
  await new Promise<void>((resolve) => {
    server = app.listen(PORT, () => resolve());
  });
});

after(async () => {
  await new Promise<void>((resolve) => {
    server.close(() => resolve());
  });
});

describe("Pabpla Ceremony API (TypeScript Suite)", () => {
  beforeEach(() => store.reset());

  test("document pagination preserves order and searches before slicing", async () => {
    const template = store.documents[0];
    store.documents = Array.from({ length: 67 }, (_, i) => ({ ...template, id: i + 1, docName: `Document ${i + 1}` }));
    const page = await (await fetch(`${BASE_URL}/api/demo/documents?page=2&limit=25`)).json() as any;
    assert.strictEqual(page.data.length, 25);
    assert.strictEqual(page.data[0].id, 26);
    assert.deepStrictEqual(page.pagination, { page: 2, limit: 25, total: 67, totalPages: 3 });
    const search = await (await fetch(`${BASE_URL}/api/demo/documents?page=1&limit=25&search=Document%2067`)).json() as any;
    assert.deepStrictEqual(search.data.map((d: any) => d.id), [67]);
    assert.strictEqual(search.pagination.total, 1);
    const legacy = await (await fetch(`${BASE_URL}/api/demo/documents`)).json() as any;
    assert.strictEqual(legacy.data.length, 67);
  });

  test("pagination clamps page and limit and tolerates invalid query values", async () => {
    const template = store.documents[0];
    store.documents = Array.from({ length: 220 }, (_, i) => ({ ...template, id: i + 1 }));
    const capped = await (await fetch(`${BASE_URL}/api/demo/documents?page=999&limit=9999`)).json() as any;
    assert.deepStrictEqual(capped.pagination, { page: 3, limit: 100, total: 220, totalPages: 3 });
    assert.strictEqual(capped.data.length, 20);
    const invalid = await (await fetch(`${BASE_URL}/api/demo/documents?page=no&limit=-1`)).json() as any;
    assert.strictEqual(invalid.pagination.page, 1);
    assert.strictEqual(invalid.pagination.limit, 25);
    const empty = await (await fetch(`${BASE_URL}/api/demo/documents?page=1&search=NO_SUCH_DOCUMENT`)).json() as any;
    assert.strictEqual(empty.pagination.total, 0);
    assert.strictEqual(empty.pagination.totalPages, 1);
    assert.deepStrictEqual(empty.data, []);
  });

  test("role and status filtering happen before document pagination", async () => {
    const template = store.documents[0];
    store.documents = Array.from({ length: 30 }, (_, i) => ({ ...template, id: i + 1, assignedToId: null, status: i < 25 ? "pending" : "assigned" }));
    const result = await (await fetch(`${BASE_URL}/api/demo/documents?page=1&limit=2&status=assigned`, {
      headers: { "x-demo-role": "coordinator_operation" }
    })).json() as any;
    assert.strictEqual(result.count, 5);
    assert.deepStrictEqual(result.data.map((d: any) => d.id), [26, 27]);
  });

  test("deleting and recreating documents never reuses an id", async () => {
    const maximumId = Math.max(...store.documents.map(d => d.id));
    for (const id of [Math.min(...store.documents.map(d => d.id)), maximumId]) {
      await fetch(`${BASE_URL}/api/demo/documents/${id}`, { method: "DELETE" });
    }
    const created = await (await fetch(`${BASE_URL}/api/demo/documents`, {
      method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ docName: "Regression", jobType: "test" })
    })).json() as any;
    assert.ok(created.data.id > maximumId);
    assert.strictEqual(new Set(store.documents.map(d => d.id)).size, store.documents.length);
  });

  test("deleting and recreating vehicles never reuses an id", async () => {
    const maximumId = Math.max(...store.vehicles.map(v => v.id));
    await fetch(`${BASE_URL}/api/demo/vehicles/${maximumId}`, { method: "DELETE" });
    const created = await (await fetch(`${BASE_URL}/api/demo/vehicles`, {
      method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ vehicleType: "test", brand: "test", plateLetters: "T", plateNumbers: "123", plateProvince: "Demo" })
    })).json() as any;
    assert.ok(created.data.id > maximumId);
  });

  test("log queries are bounded and filter actions and search before pagination", async () => {
    store.logs = [];
    store.addLog("TARGET", "Older matching log");
    for (let i = 0; i < 60; i++) store.addLog("OTHER", "Newer unrelated log");
    const defaultPage = await (await fetch(`${BASE_URL}/api/demo/logs`)).json() as any;
    assert.strictEqual(defaultPage.data.length, 50);
    const filtered = await (await fetch(`${BASE_URL}/api/demo/logs?actions=TARGET&search=older&limit=1`)).json() as any;
    assert.strictEqual(filtered.count, 1);
    assert.strictEqual(filtered.data[0].action, "TARGET");
  });

  test("log retention caps memory while ids keep increasing; reset restores counters", () => {
    const local = new MockDataStore();
    let lastId = 0;
    for (let i = 0; i <= MockDataStore.MAX_LOGS; i++) {
      const log = local.addLog("TEST", String(i));
      assert.ok(log.id > lastId);
      lastId = log.id;
    }
    assert.strictEqual(local.logs.length, MockDataStore.MAX_LOGS);
    assert.strictEqual(local.logs[0].detail, String(MockDataStore.MAX_LOGS));
    local.reset();
    assert.strictEqual(local.addLog("TEST", "after reset").id, Math.max(...local.logs.slice(1).map(l => l.id)) + 1);
  });
  test("GET / - Health Check returns 200 and TypeScript version", async () => {
    const res = await fetch(`${BASE_URL}/`);
    assert.strictEqual(res.status, 200);
    const body = await res.json() as { status: string; version: string };
    assert.strictEqual(body.status, "online");
    assert.strictEqual(body.version, "2.0.0");
  });

  test("GET /api/demo/roles - Returns all 8 simulated roles", async () => {
    const res = await fetch(`${BASE_URL}/api/demo/roles`);
    assert.strictEqual(res.status, 200);
    const body = await res.json() as { success: boolean; data: unknown[] };
    assert.strictEqual(body.success, true);
    assert.strictEqual(body.data.length, 8);
  });

  test("GET /api/demo/documents - Returns seeded document records", async () => {
    const res = await fetch(`${BASE_URL}/api/demo/documents`, {
      headers: { "x-demo-role": "super_admin" }
    });
    assert.strictEqual(res.status, 200);
    const body = await res.json() as { success: boolean; data: unknown[] };
    assert.strictEqual(body.success, true);
    assert.ok(body.data.length >= 4);
  });

  test("POST /api/demo/documents - Creates new document with Thai metadata", async () => {
    const res = await fetch(`${BASE_URL}/api/demo/documents`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "x-demo-role": "admin"
      },
      body: JSON.stringify({
        docName: "โครงการทดสอบ TypeScript API 2567",
        jobType: "พิธีการ",
        venueName: "ห้องประชุมสารบรรณกลาง",
        venueProvince: "นนทบุรี"
      })
    });

    assert.strictEqual(res.status, 201);
    const body = await res.json() as { success: boolean; data: { docName: string } };
    assert.strictEqual(body.success, true);
    assert.strictEqual(body.data.docName, "โครงการทดสอบ TypeScript API 2567");
  });

  test("POST /api/demo/reset - Restores snapshot data cleanly", async () => {
    const res = await fetch(`${BASE_URL}/api/demo/reset`, { method: "POST" });
    assert.strictEqual(res.status, 200);
    const body = await res.json() as { success: boolean };
    assert.strictEqual(body.success, true);
  });
});

