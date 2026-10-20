import { ExternalLink, LogOut, UserRound } from "lucide-react";
import { Link } from "react-router";

type Props = {
  onNavigate?: () => void;
  onSignOut: () => void;
  dropdown?: boolean;
};

export default function AdminAccountLinks({
  onNavigate,
  onSignOut,
  dropdown = false,
}: Props) {
  const style = `flex w-full items-center gap-3 rounded-lg px-3 py-1.5 text-left text-sm transition-colors ${dropdown ? "text-gray-600 hover:bg-gray-50 hover:text-gray-900" : "text-gray-400 hover:bg-gray-700/50 hover:text-white"}`;
  return (
    <div>
      <Link
        to="/"
        target="_blank"
        rel="noopener noreferrer"
        onClick={onNavigate}
        className={style}
      >
        <ExternalLink className="size-4" aria-hidden="true" />
        Visit website
      </Link>
      <Link to="/site-admin/profile" onClick={onNavigate} className={style}>
        <UserRound className="size-4" aria-hidden="true" />
        Profile settings
      </Link>
      <button
        type="button"
        onClick={() => {
          onNavigate?.();
          onSignOut();
        }}
        className={`${style} ${dropdown ? "text-red-600 hover:text-red-700" : ""}`}
      >
        <LogOut className="size-4" aria-hidden="true" />
        Sign out
      </button>
    </div>
  );
}
