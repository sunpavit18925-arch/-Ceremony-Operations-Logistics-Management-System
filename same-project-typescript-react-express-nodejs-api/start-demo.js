import { spawn } from "child_process";
import path from "path";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

console.log("===================================================================");
console.log("  ระบบบริหารจัดการงานพลับพลาพิธี (Pabpla Ceremony Management System) ");
console.log("  Full Stack Portfolio Demonstration Launcher                     ");
console.log("===================================================================");
console.log("Starting Express.js Backend on http://localhost:4000 ...");
console.log("Starting Next.js Frontend on http://localhost:3000 ...");
console.log("-------------------------------------------------------------------");

const backend = spawn("npm", ["run", "dev"], {
  cwd: path.join(__dirname, "backend"),
  shell: true,
  stdio: "inherit"
});

const frontend = spawn("npm", ["run", "dev"], {
  cwd: path.join(__dirname, "frontend"),
  shell: true,
  stdio: "inherit"
});

const cleanup = () => {
  console.log("\nShutting down portfolio demo services...");
  backend.kill();
  frontend.kill();
  process.exit();
};

process.on("SIGINT", cleanup);
process.on("SIGTERM", cleanup);

