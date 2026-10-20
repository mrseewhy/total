import Logo from "../components/Logo";
import { DashboardContext } from "../components/admin/dashboardContext";
import type { DashboardSummary } from "../components/admin/dashboardContext";
import { cmsRequest } from "../api/content";
import { useEffect } from "react";
import { useAuth } from "../auth/context";
import AdminHeader from "../components/admin/AdminHeader";
import AdminSidebarFooter from "../components/admin/AdminSidebarFooter";
import { Menu, X } from "lucide-react";
import { Link, Outlet, useLocation, useNavigate } from "react-router";
import AdminNav from "../components/admin/AdminNav";
import { adminNavigation } from "../components/admin/navigation";
import { useState } from "react";
export default function AdminDashboardLayout() {
  const [data, setData] = useState<DashboardSummary | null>(null);
  const [error, setError] = useState("");
  const [revision, setRevision] = useState(0);
  const { pathname: currentPath } = useLocation();
  useEffect(() => {
    const abort = new AbortController();
    const load = () =>
      cmsRequest("dashboard", "GET", undefined, abort.signal)
        .then((value) => {
          setData(value);
          setError("");
        })
        .catch((e) => {
          if (!abort.signal.aborted) setError(e.message);
        });
    void load();
    const timer = window.setInterval(() => {
      void load();
    }, 30000);
    const changed = () => {
      void load();
    };
    window.addEventListener("contacts-updated", changed);
    return () => {
      abort.abort();
      clearInterval(timer);
      window.removeEventListener("contacts-updated", changed);
    };
  }, [currentPath, revision]);
  const [open, setOpen] = useState(false);
  const { pathname } = useLocation();
  const navigate = useNavigate();
  const { logout } = useAuth();
  const [logoutError, setLogoutError] = useState("");
  const [signingOut, setSigningOut] = useState(false);
  const signOut = async () => {
    if (signingOut) return;
    setSigningOut(true);
    try {
      await logout();
      navigate("/site-admin/login", { replace: true });
    } catch (e) {
      setLogoutError(e instanceof Error ? e.message : "Could not sign out");
    } finally {
      setSigningOut(false);
    }
  };
  const section =
    adminNavigation.find((item) =>
      item.end
        ? pathname === item.to
        : pathname === item.to || pathname.startsWith(`${item.to}/`),
    )?.label ??
    (pathname === "/site-admin/profile" ? "Profile settings" : "Dashboard");
  return (
    <DashboardContext.Provider
      value={{ data, error, refresh: () => setRevision((v) => v + 1) }}
    >
      <div className="min-h-screen bg-gray-50 text-gray-900">
        <header className="flex items-center justify-between bg-gray-800 p-5 text-white lg:hidden">
          <Link to="/site-admin" className="text-xl font-semibold">
            <Logo variant="light" />
          </Link>
          <button
            onClick={() => setOpen(!open)}
            aria-label={open ? "Close navigation" : "Open navigation"}
            aria-expanded={open}
            aria-controls="admin-navigation"
          >
            {open ? (
              <X className="size-5" aria-hidden="true" />
            ) : (
              <Menu className="size-5" aria-hidden="true" />
            )}
          </button>
        </header>
        <aside
          className={`${open ? "flex" : "hidden"} inset-y-0 left-0 w-full flex-col overflow-y-auto bg-gray-800 p-6 text-gray-300 lg:fixed lg:flex lg:w-64`}
        >
          <Link
            to="/site-admin"
            className="mb-12 text-2xl font-semibold text-white"
          >
            <Logo variant="light" />
            <span className="ml-2 text-[10px] tracking-widest text-gray-400">
              ADMIN
            </span>
          </Link>
          <p className="mb-4 text-[10px] tracking-[.2em] text-gray-500">
            WORKSPACE
          </p>
          <AdminNav onNavigate={() => setOpen(false)} />
          <AdminSidebarFooter
            onNavigate={() => setOpen(false)}
            onSignOut={signOut}
          />
        </aside>
        <div className="lg:ml-64">
          <AdminHeader section={section} onSignOut={signOut} />
          <main className="p-5 sm:p-6">
            <p className="text-[10px] font-semibold tracking-[.2em] text-gray-400">
              YOUR WORKSPACE, SIMPLIFIED
            </p>
            <h1 className="mt-3 text-3xl font-semibold tracking-tight">
              {section}
            </h1>
            <p className="mt-3 text-sm text-gray-500">
              Manage the services behind your website.
            </p>
            {logoutError && (
              <p role="alert" className="mb-4 text-sm text-red-600">
                {logoutError}
              </p>
            )}
            <Outlet />
          </main>
        </div>
      </div>
    </DashboardContext.Provider>
  );
}
