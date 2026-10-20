import type { ManagedUser } from "../../api/users";
import ConfirmDelete from "./ConfirmDelete";
export default function DeleteUserModal({
  user,
  onClose,
  onDelete,
}: {
  user: ManagedUser;
  onClose: () => void;
  onDelete: () => Promise<void>;
}) {
  return (
    <ConfirmDelete
      title="Delete user?"
      description={`Permanently delete ${user.name} (${user.email})? Their account access will be removed. This cannot be undone.`}
      onClose={onClose}
      onConfirm={onDelete}
    />
  );
}
