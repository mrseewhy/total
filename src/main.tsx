import AuthProvider from "./auth/AuthProvider";
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./index.css";
import { Router } from "./routes/index";
import { RouterProvider } from "react-router/dom";
createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <AuthProvider><RouterProvider router={Router} /></AuthProvider>
  </StrictMode>,
);
