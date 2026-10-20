import { contentModule } from "../content/modules";
import * as z from "zod";
import type { ContentInput, ContentKind } from "../api/content";
import type { FieldErrors } from "../api/response";
export const contentSchema = z.object({
  title: z
    .string()
    .trim()
    .min(1, "Title is required.")
    .max(200, "Title is too long."),
  slug: z
    .string()
    .min(1, "Slug is required.")
    .max(200)
    .regex(
      /^[a-z0-9]+(?:-[a-z0-9]+)*$/,
      "Use lowercase letters, numbers, and hyphens in the slug.",
    ),
  excerpt: z.string().max(1000, "Excerpt is too long."),
  status: z.enum(["draft", "published", "scheduled"]),
});
export function contentErrors(
  data: ContentInput,
  kind: ContentKind,
): FieldErrors {
  const module = contentModule(kind);
  const errors: FieldErrors = {};
  const bytes = (v: string) => new TextEncoder().encode(v).length;
  const parsed = contentSchema.safeParse(data);
  if (!parsed.success)
    for (const issue of parsed.error.issues)
      errors[issue.path.join(".")] ??= issue.message;
  if (bytes(data.seo_title) > 70) errors.seo_title = "SEO title is too long.";
  if (bytes(data.seo_description) > 170)
    errors.seo_description = "Meta description is too long.";
  if (bytes(data.title) > 200) errors.title = "Title is too long.";
  if (bytes(data.excerpt) > 1000) errors.excerpt = "Excerpt is too long.";
  if (bytes(JSON.stringify(data.content)) > 1000000)
    errors.content = "Content is too large.";
  if (
    data.status === "scheduled" &&
    (!module.scheduling ||
      !data.publish_at ||
      !Number.isFinite(Date.parse(data.publish_at)) ||
      Date.parse(data.publish_at) <= Date.now())
  )
    errors.publish_at = "Choose a future publication date and time.";
  if (module.profile === "event") {
    if (data.status !== "draft") {
      if (!data.details.location?.trim())
        errors["details.location"] = "A published event needs a location.";
      if (!data.details.starts_at)
        errors["details.starts_at"] = "A published event needs a start date.";
    }
    if (
      data.details.ends_at &&
      (!data.details.starts_at ||
        Date.parse(data.details.ends_at) <= Date.parse(data.details.starts_at))
    )
      errors["details.ends_at"] = "Event end must be after its start.";
  }
  if (module.profile === "property") {
    if (!/^\d{1,12}(?:\.\d{1,2})?$/.test(data.details.price || ""))
      errors["details.price"] =
        "Enter a valid non-negative price with up to two decimals.";
    if (!/^[A-Z]{3}$/.test(data.details.currency || ""))
      errors["details.currency"] = "Enter a three-letter currency code.";
    if (data.status !== "draft" && !data.details.location?.trim())
      errors["details.location"] = "Location is required to publish.";
    for (const key of ["bedrooms", "bathrooms"] as const)
      if (
        !Number.isInteger(data.details[key]) ||
        (data.details[key] ?? 0) < 0 ||
        (data.details[key] ?? 0) > 100
      )
        errors[`details.${key}`] = `Enter ${key} between 0 and 100.`;
  }
  return errors;
}
export function validateContent(
  data: ContentInput,
  kind: ContentKind,
): string | null {
  return Object.values(contentErrors(data, kind))[0] ?? null;
}
