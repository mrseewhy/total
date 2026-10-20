import { useEffect, useRef, useState } from "react";
import { ChevronDown } from "lucide-react";
import { useAuth } from "../../auth/context";
import AdminAccountLinks from "./AdminAccountLinks";

export default function AdminAccountMenu({
  onSignOut,
}: {
  onSignOut: () => void;
}) {
  const { user } = useAuth();
  const adminAccount = { name: user?.name ?? '', email: user?.email ?? '', role: user?.role ?? '', initials: user?.name.split(/\s+/).filter(Boolean).slice(0,2).map(part => part[0]).join('').toUpperCase() ?? '' };
  const [open, setOpen] = useState(false);
  const container = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  useEffect(() => {
    if (!open) return;
    const close = (event: PointerEvent) => {
      if (!container.current?.contains(event.target as Node)) setOpen(false);
    };
    const escape = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
        trigger.current?.focus();
      }
    };
    document.addEventListener("pointerdown", close);
    document.addEventListener("keydown", escape);
    return () => {
      document.removeEventListener("pointerdown", close);
      document.removeEventListener("keydown", escape);
    };
  }, [open]);
  return (
    <div
      ref={container}
      className="relative"
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget)) setOpen(false);
      }}
    >
      <button
        ref={trigger}
        type="button"
        aria-label="Account options"
        aria-expanded={open}
        aria-controls="admin-account-options"
        onClick={() => setOpen(!open)}
        className="flex items-center gap-2 rounded-full p-1.5 transition hover:bg-gray-100"
      >
        <span className="flex size-9 items-center justify-center rounded-full bg-gray-800 text-xs font-medium text-white">
          {adminAccount.initials}
        </span>
        <ChevronDown
          className={`size-4 text-gray-400 transition-transform ${open ? "rotate-180" : ""}`}
          aria-hidden="true"
        />
      </button>
      {open && (
        <div
          id="admin-account-options"
          className="absolute top-full right-0 z-30 mt-3 w-64 rounded-xl border border-gray-200 bg-white p-2 shadow-xl shadow-gray-900/10"
        >
          <div className="mb-2 border-b border-gray-100 px-3 pt-3 pb-4">
            <div className="flex items-center justify-between gap-2">
              <p className="text-sm font-semibold text-gray-900">
                {adminAccount.name}
              </p>
              <span className="rounded bg-green-50 px-2 py-1 text-[10px] font-medium text-green-700">
                {adminAccount.role}
              </span>
            </div>
            <p className="mt-2 break-all text-xs text-gray-500">
              {adminAccount.email}
            </p>
          </div>
          <AdminAccountLinks
            dropdown
            onNavigate={() => setOpen(false)}
            onSignOut={onSignOut}
          />
        </div>
      )}
    </div>
  );
}
