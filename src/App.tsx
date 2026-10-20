import { RouterProvider } from "react-router/dom";
import { Router } from "./routes";

export default function App() {
  return <RouterProvider router={Router} />;
}
