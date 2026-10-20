import { createPortal } from "react-dom";
import { useEffect, useId, useRef } from "react";
import type { ReactNode } from "react";
import { X } from "lucide-react";
export default function AdminModal({
  title,
  children,
  onClose,
  busy = false,
  wide = false,
}: {
  title: string;
  children: ReactNode;
  onClose: () => void;
  busy?: boolean;
  wide?: boolean;
}) {
  const dialog = useRef<HTMLDialogElement>(null);
  const id = useId();
  useEffect(() => {
    dialog.current?.showModal();
  }, []);
  return createPortal(
    <dialog
      ref={dialog}
      onSubmit={(event) => event.stopPropagation()}
      onChange={(event) => event.stopPropagation()}
      aria-labelledby={id}
      onCancel={(event) => {
        event.preventDefault();
        if (!busy) onClose();
      }}
      className={`fixed inset-0 m-auto max-h-[90vh] w-[calc(100%-2rem)] ${wide ? "max-w-5xl" : "max-w-lg"} overflow-y-auto rounded-2xl border-0 bg-white p-6 text-gray-900 shadow-xl backdrop:bg-gray-950/50`}
    >
      <div className="mb-6 flex items-center justify-between">
        <h2 id={id} className="text-lg font-semibold">
          {title}
        </h2>
        <button
          type="button"
          disabled={busy}
          aria-label="Close dialog"
          onClick={onClose}
          className="rounded p-1 hover:bg-gray-100"
        >
          <X className="size-5" />
        </button>
      </div>
      {children}
    </dialog>,
    document.body,
  );
}
