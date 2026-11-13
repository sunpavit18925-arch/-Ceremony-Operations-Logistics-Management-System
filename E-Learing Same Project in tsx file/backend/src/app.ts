import express, { Application, Request, Response } from "express";
import cors from "cors";
import morgan from "morgan";
import demoRoutes from "./routes/demoRoutes.js";
import { notFoundHandler, errorHandler } from "./middlewares/errorMiddleware.js";

export const createApp = (): Application => {
  const app: Application = express();

  // Middleware
  app.use(cors({
    origin: ["http://localhost:3000", "http://127.0.0.1:3000", "http://localhost:3001", "http://127.0.0.1:3001"],
    credentials: true
  }));
  app.use(express.json());
  app.use(morgan("dev"));

  // Health Check & Documentation Root
  app.get("/", (req: Request, res: Response) => {
    res.json({
      status: "online",
      service: "Pabpla Ceremony Management Demo API (TypeScript)",
      version: "2.0.0",
      description: "Express.js RESTful API serving 100% simulated data with full type safety for portfolio demonstrations",
      endpoints: "/api/demo"
    });
  });

  // REST API Routes
  app.use("/api/demo", demoRoutes);

  // Error handling
  app.use(notFoundHandler);
  app.use(errorHandler);

  return app;
};

