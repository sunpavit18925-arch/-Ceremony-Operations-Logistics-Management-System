import assert from "node:assert/strict";
import { test } from "node:test";
import vm from "node:vm";

let instance = 0;

async function browser({ saved, dark = false, blocked = false } = {}) {
  const values = new Map(saved === undefined ? [] : [["coms-theme", saved]]);
  const attributes = new Map();
  const media = new EventTarget();
  media.matches = dark;
  const window = new EventTarget();
  window.matchMedia = () => media;
  const document = {
    documentElement: {
      setAttribute: (key, value) => attributes.set(key, value),
      getAttribute: (key) => attributes.get(key),
    },
  };
  const localStorage = {
    getItem(key) {
      if (blocked) throw new Error("Storage blocked");
      return values.get(key) ?? null;
    },
    setItem(key, value) {
      if (blocked) throw new Error("Storage blocked");
      values.set(key, value);
    },
  };
  Object.assign(globalThis, { window, document, localStorage });
  const theme = await import(`../src/lib/theme.ts?test=${++instance}`);
  vm.runInNewContext(theme.themeInitScript, { window, document, localStorage });
  return {
    theme, values, media,
    storage(key = "coms-theme") {
      const event = new Event("storage");
      Object.defineProperty(event, "key", { value: key });
      window.dispatchEvent(event);
    },
  };
}

test("applies the system theme before hydration and follows system changes", async () => {
  const { theme, media } = await browser({ dark: true });
  assert.equal(theme.getTheme(), "dark");
  const unsubscribe = theme.subscribeToTheme(() => {});
  media.matches = false;
  media.dispatchEvent(new Event("change"));
  assert.equal(theme.getTheme(), "light");
  unsubscribe();
});

test("saved light preference overrides a dark system theme on reload", async () => {
  const { theme } = await browser({ saved: "light", dark: true });
  assert.equal(theme.getTheme(), "light");
});

test("toggle persists the selection and ignores subsequent system changes", async () => {
  const { theme, values, media } = await browser();
  let notifications = 0;
  const unsubscribe = theme.subscribeToTheme(() => notifications++);
  theme.setTheme("dark");
  assert.equal(theme.getTheme(), "dark");
  assert.equal(values.get("coms-theme"), "dark");
  assert.ok(notifications > 1);
  media.matches = false;
  media.dispatchEvent(new Event("change"));
  assert.equal(theme.getTheme(), "dark");
  theme.setTheme("light");
  assert.equal(theme.getTheme(), "light");
  assert.equal(values.get("coms-theme"), "light");
  unsubscribe();
});

test("synchronizes another tab's selection and resets to system after clearing storage", async () => {
  const { theme, values, storage } = await browser({ dark: true });
  const unsubscribe = theme.subscribeToTheme(() => {});
  theme.setTheme("dark");
  values.set("coms-theme", "light");
  storage();
  assert.equal(theme.getTheme(), "light");
  values.clear();
  storage(null);
  assert.equal(theme.getTheme(), "dark");
  unsubscribe();
});

test("blocked storage still allows switching both ways", async () => {
  const { theme } = await browser({ blocked: true, dark: true });
  const unsubscribe = theme.subscribeToTheme(() => {});
  assert.equal(theme.getTheme(), "dark");
  theme.setTheme("light");
  theme.syncTheme();
  assert.equal(theme.getTheme(), "light");
  theme.setTheme("dark");
  assert.equal(theme.getTheme(), "dark");
  unsubscribe();
});

test("invalid stored values use the system theme", async () => {
  const { theme } = await browser({ saved: "invalid", dark: true });
  assert.equal(theme.getTheme(), "dark");
});

test("unsubscribing removes browser event listeners", async () => {
  const { theme, media, values, storage } = await browser();
  let notifications = 0;
  const unsubscribe = theme.subscribeToTheme(() => notifications++);
  const before = notifications;
  unsubscribe();
  values.set("coms-theme", "dark");
  storage();
  media.matches = true;
  media.dispatchEvent(new Event("change"));
  assert.equal(notifications, before);
});
