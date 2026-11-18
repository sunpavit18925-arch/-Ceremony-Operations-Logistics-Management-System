"use client";

import { useLayoutEffect, useSyncExternalStore } from "react";
import { getServerTheme, getTheme, setTheme, subscribeToTheme, syncTheme } from "@/lib/theme";

export default function ThemeToggle() {
  const theme = useSyncExternalStore(subscribeToTheme, getTheme, getServerTheme);
  const isDark = theme === "dark";

  // Restore the attribute if React Strict Mode resets <html> in development.
  useLayoutEffect(() => { syncTheme(); }, []);

  return (
    <button
      type="button"
      className="theme-toggle"
      aria-label="โหมดมืด"
      aria-pressed={isDark}
      title={isDark ? "เปลี่ยนเป็นโหมดสว่าง" : "เปลี่ยนเป็นโหมดมืด"}
      onClick={() => setTheme(isDark ? "light" : "dark")}
    >
      <span className="theme-toggle-to-dark">
        <i className="bi bi-moon-stars" aria-hidden="true" />
        <span>Dark mode</span>
      </span>
      <span className="theme-toggle-to-light">
        <i className="bi bi-sun" aria-hidden="true" />
        <span>Light mode</span>
      </span>
    </button>
  );
}
