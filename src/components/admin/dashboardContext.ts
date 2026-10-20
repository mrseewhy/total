import { createContext, useContext } from "react";
export type DashboardSummary = {
  counts: { pages: number; unread: number; users?: number };
  pages: { path: string; label: string }[];
  modules: { id: string; label: string; total: number }[];
};
export const DashboardContext = createContext<{
  data: DashboardSummary | null;
  error: string;
  refresh: () => void;
}>({ data: null, error: "", refresh: () => {} });
export const useDashboard = () => useContext(DashboardContext);
