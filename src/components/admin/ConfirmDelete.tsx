import { useForm } from "../../forms/useForm";
import { FormFeedback } from "../forms/Form";
import AdminModal from "./AdminModal";
export default function ConfirmDelete({
  title,
  description,
  onConfirm,
  onClose,
}: {
  title: string;
  description: string;
  onConfirm: () => Promise<void>;
  onClose: () => void;
}) {
  const form = useForm();
  const { busy } = form;
  async function remove() {
    await form.submit(async () => {
      await onConfirm();
      onClose();
    });
  }
  return (
    <AdminModal title={title} onClose={onClose} busy={busy}>
      <p className="text-sm leading-6 text-gray-600">{description}</p>
      <FormFeedback state={form} />
      <div className="mt-6 flex justify-end gap-3">
        <button
          autoFocus
          disabled={busy}
          onClick={onClose}
          className="rounded-lg border border-gray-200 px-4 py-2 text-sm"
        >
          Cancel
        </button>
        <button
          disabled={busy}
          onClick={() => void remove()}
          className="rounded-lg bg-red-600 px-4 py-2 text-sm text-white disabled:opacity-50"
        >
          {busy ? "Deleting…" : "Delete permanently"}
        </button>
      </div>
    </AdminModal>
  );
}
