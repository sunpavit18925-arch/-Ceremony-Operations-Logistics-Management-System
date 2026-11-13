import { test, describe, before, after } from "node:test";
import assert from "node:assert";
import { Server } from "node:http";
import { createApp } from "../src/app.js";

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

