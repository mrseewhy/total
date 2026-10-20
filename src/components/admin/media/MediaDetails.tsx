import { toast } from "sonner";
import { mediaDetailsSchema } from "../../../validation/media";
import {
  FormValidationError,
  issueFields,
  useForm,
} from "../../../forms/useForm";
import { Form, FormFeedback, FieldError } from "../../forms/Form";
import { useState } from "react";
import { Link } from "react-router";
import AdminModal from "../AdminModal";
import ConfirmDelete from "../ConfirmDelete";
import { cmsRequest, formatDate } from "../../../api/content";
import type { MediaItem } from "../../../api/content";
export default function MediaDetails({
  media,
  onClose,
  onChanged,
}: {
  media: MediaItem;
  onClose: () => void;
  onChanged: () => void;
}) {
  const form = useForm();
  const { busy } = form;
  const [deleting, setDeleting] = useState(false);
  async function save(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.currentTarget));
    await form.submit(async () => {
      const parsed = mediaDetailsSchema.safeParse(data);
      if (!parsed.success)
        throw new FormValidationError(issueFields(parsed.error.issues));
      await cmsRequest(`media/${media.id}`, "PATCH", parsed.data);
      onChanged();
    }, "Media details saved.");
  }

  return (
    <>
      <AdminModal title="Attachment details" onClose={onClose} busy={busy}>
        <img
          src={media.url}
          alt={media.alt_text}
          className="max-h-64 w-full rounded-lg bg-gray-50 object-contain"
        />
        <p className="mt-3 break-all text-xs text-gray-500">
          {media.original_name} · {media.width} × {media.height} ·{" "}
          {formatDate(media.created_at)}
        </p>
        <Form state={form} onSubmit={save} className="mt-5 space-y-4">
          {(["title", "alt_text", "caption"] as const).map((name) => (
            <label key={name} className="block text-sm">
              {
                {
                  title: "Title",
                  alt_text: "Alternative text",
                  caption: "Caption",
                }[name]
              }
              <input
                {...form.attributes(name)}
                name={name}
                required={name === "title"}
                maxLength={
                  name === "title" ? 200 : name === "alt_text" ? 500 : 1000
                }
                defaultValue={media[name]}
                className="auth-input"
              />
              <FieldError state={form} name={name} />
            </label>
          ))}
          <label className="block text-xs text-gray-500">
            File URL
            <input
              aria-label="File URL"
              readOnly
              value={media.url}
              className="auth-input"
            />
          </label>
          <FormFeedback state={form} />
          <div className="flex justify-between gap-4">
            <button
              type="button"
              onClick={() => setDeleting(true)}
              disabled={busy || !!media.used_by.length}
              className="text-xs text-red-600 disabled:opacity-40"
            >
              Delete permanently
            </button>
            <button
              disabled={busy}
              className="rounded-lg bg-gray-800 px-4 py-2 text-xs text-white"
            >
              {busy ? "Saving…" : "Save details"}
            </button>
          </div>
        </Form>
        {media.used_by.length > 0 && (
          <div className="mt-5 border-t border-gray-200 pt-4 text-xs text-gray-500">
            <p>
              This image is in use. Remove it from these items before deleting:
            </p>
            {media.used_by.map((use) => (
              <Link
                className="mt-2 block underline"
                key={`${use.content_kind}-${use.content_id}`}
                to={`/site-admin/${use.content_kind}/${use.content_id}/edit`}
              >
                {use.content_kind} #{use.content_id}
              </Link>
            ))}
          </div>
        )}
      </AdminModal>
      {deleting && (
        <ConfirmDelete
          title="Delete image?"
          description="The image file and media record will be permanently removed."
          onClose={() => setDeleting(false)}
          onConfirm={async () => {
            await cmsRequest(`media/${media.id}`, "DELETE");
            toast.success("Image deleted.");
            onChanged();
            onClose();
          }}
        />
      )}
    </>
  );
}
