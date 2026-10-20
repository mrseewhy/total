import AdminAccountMenu from "./AdminAccountMenu";
export default function AdminHeader({
  section,
  onSignOut,
}: {
  section: string;
  onSignOut: () => void;
}) {
  return (
    <header className="flex items-center justify-between gap-3 border-b border-gray-200 bg-white px-5 py-4 sm:px-6">
      <p className="text-sm text-gray-400">
        Workspace <span className="mx-3">/</span>
        <span className="font-medium text-gray-800">{section}</span>
      </p>
      <AdminAccountMenu onSignOut={onSignOut} />
    </header>
  );
}
