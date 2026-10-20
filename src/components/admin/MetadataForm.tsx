import { FormValidationError, useForm } from "../../forms/useForm";
import { Form, FormFeedback } from "../forms/Form";
import {
  saveMetadata,
  type PageMetadata,
  type SeoSettings,
} from "../../api/metadata";

type Field = {
  name: string;
  label: string;
  limit: number;
  required?: boolean;
  placeholder?: string;
};
export default function MetadataForm({
  value,
  path,
  label,
  onSaved,
}: {
  value: PageMetadata | SeoSettings;
  path?: string;
  label: string;
  onSaved?: () => void;
}) {
  const global = path === undefined;
  const formState = useForm();
  const { busy, errors, setErrors, setMessage } = formState;
  const fields: Field[] = global
    ? [
        { name: "site_name", label: "Site name", limit: 100, required: true },
        { name: "description", label: "Default meta description", limit: 170 },
        {
          name: "default_image",
          label: "Default sharing image URL",
          limit: 2000,
          placeholder: "https://example.com/uploads/image.jpg",
        },
        {
          name: "language",
          label: "Content language",
          limit: 20,
          required: true,
          placeholder: "en",
        },
        {
          name: "locale",
          label: "Social metadata locale",
          limit: 20,
          required: true,
          placeholder: "en_NG",
        },
      ]
    : [
        {
          name: "title",
          label: "SEO title",
          limit: 70,
          placeholder: "Leave blank to use the page default",
        },
        {
          name: "description",
          label: "Meta description",
          limit: 170,
          placeholder: "Leave blank to use the page default",
        },
        {
          name: "image",
          label: "Sharing image URL",
          limit: 2000,
          placeholder: "Leave blank to use the default sharing image",
        },
      ];
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const element = event.currentTarget;
    await formState.submit(async () => {
      const form = new FormData(element);
      const data: Record<string, unknown> = {
        scope: global ? "settings" : "page",
        ...(global ? {} : { path }),
        noindex: form.has("noindex"),
      };
      const next: Record<string, string> = {};
      for (const field of fields) {
        const text = String(form.get(field.name) ?? "").trim();
        data[field.name] = text;
        if (field.required && !text)
          next[field.name] = `${field.label} is required.`;
        else if (new TextEncoder().encode(text).length > field.limit)
          next[field.name] = `Use no more than ${field.limit} bytes.`;
        if (text && ["image", "default_image"].includes(field.name)) {
          try {
            const url = new URL(text);
            if (!["http:", "https:"].includes(url.protocol)) throw new Error();
          } catch {
            next[field.name] = "Enter a full HTTP or HTTPS image URL.";
          }
        }
        if (
          field.name === "language" &&
          !/^[a-z]{2,3}(?:-[a-zA-Z]{2,8})?$/.test(text)
        )
          next.language = "Use a language code such as en.";
        if (field.name === "locale" && !/^[a-z]{2,3}_[A-Z]{2}$/.test(text))
          next.locale = "Use a locale such as en_NG.";
      }
      if (Object.keys(next).length) throw new FormValidationError(next);
      await saveMetadata(data);
      onSaved?.();
    }, "SEO metadata saved.");
  }

  return (
    <Form
      state={formState}
      noValidate
      onSubmit={submit}
      className="rounded-xl border border-gray-200 bg-white p-6"
    >
      <h2 className="text-lg font-semibold">{label}</h2>
      <p className="mt-2 text-sm leading-6 text-gray-500">
        {global
          ? "Defaults for search and social previews. Existing page-specific metadata takes priority. These values do not change the visible website name or page content."
          : `Search and sharing metadata for ${path}. Leave a text field blank to restore its fallback. Page content and layout stay in React.`}
      </p>
      <fieldset disabled={busy} className="mt-6 space-y-5">
        {fields.map((field) => (
          <div key={field.name}>
            <label
              htmlFor={`${path ?? "global"}-${field.name}`}
              className="block text-sm font-medium"
            >
              {field.label}
            </label>
            {field.name === "description" ? (
              <textarea
                id={`${path ?? "global"}-${field.name}`}
                name={field.name}
                defaultValue={String(
                  value[field.name as keyof typeof value] ?? "",
                )}
                placeholder={field.placeholder}
                rows={3}
                aria-invalid={!!errors[field.name]}
                aria-describedby={
                  errors[field.name]
                    ? `${path ?? "global"}-${field.name}-error`
                    : undefined
                }
                onChange={() => {
                  setErrors((previous) => ({ ...previous, [field.name]: "" }));
                  setMessage("");
                }}
                className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              />
            ) : (
              <input
                id={`${path ?? "global"}-${field.name}`}
                name={field.name}
                defaultValue={String(
                  (value as unknown as Record<string, unknown>)[field.name] ??
                    "",
                )}
                required={field.required}
                placeholder={field.placeholder}
                type={
                  ["image", "default_image"].includes(field.name)
                    ? "url"
                    : "text"
                }
                aria-invalid={!!errors[field.name]}
                aria-describedby={
                  errors[field.name]
                    ? `${path ?? "global"}-${field.name}-error`
                    : undefined
                }
                onChange={() => {
                  setErrors((previous) => ({ ...previous, [field.name]: "" }));
                  setMessage("");
                }}
                className="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              />
            )}
            {errors[field.name] && (
              <p
                id={`${path ?? "global"}-${field.name}-error`}
                role="alert"
                className="mt-1 text-xs text-red-600"
              >
                {errors[field.name]}
              </p>
            )}
          </div>
        ))}
        <label className="flex items-start gap-3 text-sm">
          <input
            type="checkbox"
            name="noindex"
            defaultChecked={value.noindex}
            onChange={() => setMessage("")}
            className="mt-1"
          />
          <span>
            {global
              ? "Discourage indexing across the website"
              : "Exclude this page from search indexing"}
            <span className="mt-1 block text-xs leading-5 text-gray-500">
              {global
                ? "Localhost and server-level indexing restrictions always remain in force."
                : "Removes this page from the sitemap and adds noindex metadata."}
            </span>
          </span>
        </label>
        <FormFeedback state={formState} />
        <button
          disabled={busy}
          className="rounded-lg bg-gray-800 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50"
        >
          {busy ? "Saving…" : "Save metadata"}
        </button>
      </fieldset>
    </Form>
  );
}
