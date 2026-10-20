import { createContext, useContext } from "react";
import type { AuthUser } from "../api/auth";
export type AuthState = {
  user: AuthUser | null;
  twoFactor: boolean;
  loading: boolean;
  error: string;
  refresh: () => Promise<void>;
  login: (email: string, password: string) => Promise<boolean>;
  verifyTwoFactor: (code: string) => Promise<void>;
  cancelTwoFactor: () => Promise<void>;
  logout: () => Promise<void>;
  setUser: (user: AuthUser) => void;
};
export const AuthContext = createContext<AuthState | null>(null);
export function useAuth() {
  const value = useContext(AuthContext);
  if (!value) throw new Error("AuthProvider is required");
  return value;
}
