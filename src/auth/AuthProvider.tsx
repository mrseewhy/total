import { useEffect, useState } from "react";
import type { ReactNode } from "react";
import { authAction, getSession } from "../api/auth";
import type { AuthUser } from "../api/auth";
import { AuthContext } from "./context";
export default function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [twoFactor, setTwoFactor] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  async function refresh() {
    try {
      const result = await getSession();
      setUser(result.user);
      setTwoFactor(!!result.two_factor_pending);
      setError("");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not check session");
    } finally {
      setLoading(false);
    }
  }
  useEffect(() => {
    let active = true;
    getSession()
      .then((result) => {
        if (active) {
          setUser(result.user);
          setTwoFactor(!!result.two_factor_pending);
          setError("");
        }
      })
      .catch((e) => {
        if (active) setError(e.message);
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    const expired = () => {
      setUser(null);
      setTwoFactor(false);
    };
    window.addEventListener("auth:expired", expired);
    return () => {
      active = false;
      window.removeEventListener("auth:expired", expired);
    };
  }, []);
  async function login(email: string, password: string) {
    const result = await authAction("login", { email, password });
    setUser(result.user ?? null);
    setTwoFactor(!!result.requires_two_factor);
    setError("");
    return !result.requires_two_factor;
  }
  async function verifyTwoFactor(code: string) {
    const result = await authAction("two-factor/verify", { code });
    setUser(result.user);
    setTwoFactor(false);
    setError("");
  }
  async function cancelTwoFactor() {
    await authAction("two-factor/cancel");
    setTwoFactor(false);
  }
  async function logout() {
    await authAction("logout");
    setUser(null);
    setTwoFactor(false);
  }
  return (
    <AuthContext.Provider
      value={{
        user,
        twoFactor,
        loading,
        error,
        refresh,
        login,
        verifyTwoFactor,
        cancelTwoFactor,
        logout,
        setUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}
