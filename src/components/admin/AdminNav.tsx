import { useDashboard } from "./dashboardContext";
import { useAuth } from "../../auth/context";
import { NavLink } from "react-router";
import { adminNavigation } from "./navigation";

type AdminNavProps = { onNavigate?: () => void };

export default function AdminNav({ onNavigate }: AdminNavProps) {
  const { data } = useDashboard();
  const { user } = useAuth();
  return (
    <nav
      id="admin-navigation"
      aria-label="Admin navigation"
      className="space-y-1"
    >
      {adminNavigation
        .filter((item) => !item.adminOnly || user?.role === "Admin")
        .map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.end}
            onClick={onNavigate}
            className={({ isActive }) =>
              `flex items-center gap-3 rounded-lg px-3 py-2 text-sm ${isActive ? "bg-gray-700 text-white" : "hover:bg-gray-700/50"}`
            }
          >
            <item.icon
              aria-hidden="true"
              className="size-5 shrink-0"
              strokeWidth={1.75}
            />
            <span className="flex-1">{item.label}</span>
            {item.to === "/site-admin/contacts" && !!data?.counts.unread && (
              <span
                aria-label={`${data.counts.unread} unread inquiries`}
                className="rounded-full bg-green-400 px-2 py-0.5 text-[10px] font-semibold text-gray-900"
              >
                {data.counts.unread}
              </span>
            )}
          </NavLink>
        ))}
    </nav>
  );
}
