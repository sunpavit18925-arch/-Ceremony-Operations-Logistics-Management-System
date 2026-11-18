export type Theme = "light" | "dark";

export const THEME_STORAGE_KEY = "coms-theme";
const THEME_CHANGE_EVENT = "coms-theme-change";
let selectedTheme: Theme | null = null;

// Runs in <head> before the first paint, including when React has not loaded yet.
export const themeInitScript = `(() => {
  let theme;
  try { theme = localStorage.getItem("${THEME_STORAGE_KEY}"); } catch {}
  if (theme !== "light" && theme !== "dark") {
    theme = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  }
  document.documentElement.setAttribute("data-bs-theme", theme);
})();`;

function preferredTheme(): Theme {
  if (selectedTheme) return selectedTheme;
  try {
    const saved = localStorage.getItem(THEME_STORAGE_KEY);
    if (saved === "light" || saved === "dark") return saved;
  } catch {
    // The toggle still works when the browser blocks persistent storage.
  }
  return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}

export function syncTheme() {
  document.documentElement.setAttribute("data-bs-theme", preferredTheme());
}

export function getTheme(): Theme {
  return document.documentElement.getAttribute("data-bs-theme") === "dark" ? "dark" : "light";
}

export function getServerTheme(): Theme {
  return "light";
}

export function setTheme(theme: Theme) {
  selectedTheme = theme;
  document.documentElement.setAttribute("data-bs-theme", theme);
  try {
    localStorage.setItem(THEME_STORAGE_KEY, theme);
  } catch {
    // Keep the selection in memory for this visit when storage is unavailable.
  }
  window.dispatchEvent(new Event(THEME_CHANGE_EVENT));
}

export function subscribeToTheme(onChange: () => void) {
  const media = window.matchMedia("(prefers-color-scheme: dark)");
  const update = () => {
    syncTheme();
    onChange();
  };
  const onStorage = (event: StorageEvent) => {
    if (event.key === THEME_STORAGE_KEY || event.key === null) {
      selectedTheme = null;
      update();
    }
  };

  window.addEventListener(THEME_CHANGE_EVENT, update);
  window.addEventListener("storage", onStorage);
  media.addEventListener("change", update);
  update();

  return () => {
    window.removeEventListener(THEME_CHANGE_EVENT, update);
    window.removeEventListener("storage", onStorage);
    media.removeEventListener("change", update);
  };
}
