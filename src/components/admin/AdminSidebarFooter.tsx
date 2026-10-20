import AdminAccountLinks from "./AdminAccountLinks";

type Props = { onNavigate: () => void; onSignOut: () => void };
export default function AdminSidebarFooter(props: Props) {
  return (
    <div className="mt-8 border-t border-gray-700 pt-4 lg:mt-auto">
      <AdminAccountLinks {...props} />
    </div>
  );
}
