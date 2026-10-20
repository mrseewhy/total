import { FormValidationError, useForm } from "../../../forms/useForm";
import { Form, FormFeedback, FieldError } from "../../forms/Form";
import { toast } from "sonner";
import { contentModule } from "../../../content/modules";
import { lazy, Suspense, useEffect, useState } from "react";
import { Link, useBeforeUnload, useBlocker, useNavigate } from "react-router";
import type {
  ContentInput,
  ContentItem,
  ContentKind,
} from "../../../api/content";
import { saveContent, utcDate } from "../../../api/content";
import { contentErrors } from "../../../validation/content";
import MediaField from "./MediaField";
import AdminModal from "../AdminModal";
const RichTextEditor = lazy(() => import("./RichTextEditor"));
function localInput(date: string | null | undefined) {
  if (!date) return "";
  const value = new Date(utcDate(date));
  return new Date(value.getTime() - value.getTimezoneOffset() * 60000)
    .toISOString()
    .slice(0, 16);
}
function iso(value: string) {
  return value ? new Date(value).toISOString() : null;
}
export default function ContentEditor({
  kind,
  initial,
}: {
  kind: ContentKind;
  initial?: ContentItem;
}) {
  const module = contentModule(kind);
  const profile = module.profile;
  const navigate = useNavigate();
  const [seoTitle, setSeoTitle] = useState(initial?.seo_title ?? "");
  const [seoDescription, setSeoDescription] = useState(
    initial?.seo_description ?? "",
  );
  const [seoNoindex, setSeoNoindex] = useState(initial?.seo_noindex ?? false);
  const [title, setTitle] = useState(initial?.title ?? "");
  const [slug, setSlug] = useState(initial?.slug ?? "");
  const [slugEdited, setSlugEdited] = useState(!!initial);
  const [excerpt, setExcerpt] = useState(initial?.excerpt ?? "");
  const [blocks, setBlocks] = useState(initial?.content ?? []);
  const [status, setStatus] = useState<ContentInput["status"]>(
    initial?.effective_status ?? "draft",
  );
  const [publishAt, setPublishAt] = useState(localInput(initial?.publish_at));
  const [featured, setFeatured] = useState<number[]>(
    initial?.featured_media_id ? [initial.featured_media_id] : [],
  );
  const [location, setLocation] = useState(initial?.details.location ?? "");
  const [starts, setStarts] = useState(localInput(initial?.details.starts_at));
  const [ends, setEnds] = useState(localInput(initial?.details.ends_at));
  const [price, setPrice] = useState(initial?.details.price ?? "0");
  const [currency, setCurrency] = useState(initial?.details.currency ?? "NGN");
  const [listingType, setListingType] = useState<"sale" | "rent">(
    initial?.details.listing_type ?? "sale",
  );
  const [availability, setAvailability] = useState<
    "available" | "under_offer" | "sold" | "rented"
  >(initial?.details.availability ?? "available");
  const [bedrooms, setBedrooms] = useState(initial?.details.bedrooms ?? 0);
  const [bathrooms, setBathrooms] = useState(initial?.details.bathrooms ?? 0);
  const [gallery, setGallery] = useState(initial?.details.gallery_ids ?? []);
  const form = useForm();
  const { busy } = form;
  const [dirty, setDirty] = useState(false);
  const [saved, setSaved] = useState(false);
  const blocker = useBlocker(dirty && !saved);
  useBeforeUnload((event) => {
    if (dirty && !saved) {
      event.preventDefault();
      event.returnValue = "";
    }
  });
  useEffect(() => {
    if (saved) navigate(`/site-admin/${kind}`, { replace: true });
  }, [saved, navigate, kind]);
  const change = () => {
    setDirty(true);
  };
  async function submit(event: React.FormEvent) {
    event.preventDefault();
    if (event.target !== event.currentTarget || busy) return;

    await form.submit(async () => {
      const data: ContentInput = {
        seo_title: seoTitle,
        seo_description: seoDescription,
        seo_noindex: seoNoindex,
        title: title.trim(),
        slug,
        excerpt,
        content: blocks,
        status,
        publish_at: status === "scheduled" ? iso(publishAt) : null,
        featured_media_id: featured[0] ?? null,
        details:
          profile === "event"
            ? { location, starts_at: iso(starts), ends_at: iso(ends) }
            : profile === "property"
              ? {
                  location,
                  price,
                  currency,
                  listing_type: listingType,
                  availability,
                  bedrooms,
                  bathrooms,
                  gallery_ids: gallery,
                }
              : {},
      };
      const invalid = contentErrors(data, kind);
      if (Object.keys(invalid).length) throw new FormValidationError(invalid);
      await saveContent(kind, data, initial?.id);
      toast.success(`${module.label} saved.`);
      setSaved(true);
      setDirty(false);
    });
  }
  return (
    <>
      <Form state={form} onSubmit={submit} onChange={change} className="mt-6">
        <div className="sticky top-0 z-30 mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white/95 p-4 shadow-sm backdrop-blur">
          <div>
            <Link to={`/site-admin/${kind}`} className="text-xs text-gray-500">
              ← All {kind}
            </Link>
            <h2 className="mt-1 text-sm font-semibold">
              {initial ? "Edit" : "Create"} {module.singular}
            </h2>
          </div>
          <div className="flex items-center gap-4">
            {dirty && (
              <span className="text-[10px] text-amber-700">
                Unsaved changes
              </span>
            )}
            <button
              disabled={busy}
              className="rounded-lg bg-gray-800 px-4 py-2.5 text-xs text-white disabled:opacity-50"
            >
              {busy
                ? "Saving…"
                : status === "draft"
                  ? "Save draft"
                  : status === "scheduled"
                    ? "Schedule post"
                    : "Publish"}
            </button>
          </div>
        </div>
        <FormFeedback state={form} />
        <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_280px]">
          <div className="min-w-0 space-y-6">
            <section className="rounded-xl border border-gray-200 bg-white p-5">
              <label className="block text-xs font-medium">
                Title
                <input
                  required
                  maxLength={200}
                  name="title"
                  {...form.attributes("title")}
                  value={title}
                  onChange={(e) => {
                    setTitle(e.target.value);
                    if (!slugEdited)
                      setSlug(
                        e.target.value
                          .toLowerCase()
                          .replace(/[^a-z0-9]+/g, "-")
                          .replace(/^-|-$/g, ""),
                      );
                  }}
                  className="auth-input text-lg"
                  placeholder="Give it a title"
                />
                <FieldError state={form} name="title" />
              </label>
              <label className="mt-5 block text-xs font-medium">
                Slug
                <input
                  required
                  maxLength={200}
                  pattern="[a-z0-9]+(-[a-z0-9]+)*"
                  name="slug"
                  {...form.attributes("slug")}
                  value={slug}
                  onChange={(e) => {
                    setSlugEdited(true);
                    setSlug(e.target.value);
                  }}
                  className="auth-input"
                />
                <FieldError state={form} name="slug" />
              </label>
              <label className="mt-5 block text-xs font-medium">
                Excerpt
                <textarea
                  maxLength={1000}
                  rows={3}
                  name="excerpt"
                  {...form.attributes("excerpt")}
                  value={excerpt}
                  onChange={(e) => setExcerpt(e.target.value)}
                  className="auth-input resize-y"
                  placeholder="A short introduction"
                />
                <FieldError state={form} name="excerpt" />
              </label>
            </section>
            <section
              aria-label="Content editor"
              className="rounded-xl border border-gray-200 bg-white"
            >
              <Suspense
                fallback={
                  <p className="p-8 text-sm text-gray-500">Loading editor…</p>
                }
              >
                <RichTextEditor
                  readOnly={busy}
                  initialContent={initial?.content ?? []}
                  onChange={(content) => {
                    form.clear("content");
                    setBlocks(content);
                    setDirty(true);
                  }}
                />
              </Suspense>
              <FieldError state={form} name="content" />
            </section>
          </div>
          <aside className="space-y-5">
            <section className="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
              <h3 className="text-sm font-semibold">Publication</h3>
              <label className="block text-xs">
                Status
                <select
                  name="status"
                  {...form.attributes("status")}
                  value={status}
                  onChange={(e) =>
                    setStatus(e.target.value as ContentInput["status"])
                  }
                  className="auth-input"
                >
                  <option value="draft">Draft</option>
                  <option value="published">Published</option>
                  {module.scheduling && (
                    <option value="scheduled">Scheduled</option>
                  )}
                </select>
                <FieldError state={form} name="status" />
              </label>
              {status === "scheduled" && (
                <label className="block text-xs">
                  Publish date and time
                  <input
                    aria-label="Publish date and time"
                    type="datetime-local"
                    required
                    name="publish_at"
                    {...form.attributes("publish_at")}
                    value={publishAt}
                    onChange={(e) => setPublishAt(e.target.value)}
                    className="auth-input"
                  />
                  <FieldError state={form} name="publish_at" />
                  <span className="mt-2 block text-[10px] text-gray-400">
                    Your local timezone:{" "}
                    {Intl.DateTimeFormat().resolvedOptions().timeZone}
                  </span>
                </label>
              )}
            </section>
            <section className="rounded-xl border border-gray-200 bg-white p-5">
              <MediaField
                label="Featured image"
                ids={featured}
                onChange={(ids) => {
                  form.clear("featured_media_id");
                  setFeatured(ids);
                  change();
                }}
              />
              <FieldError state={form} name="featured_media_id" />
            </section>
            <section className="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
              <h3 className="text-sm font-semibold">Search & social preview</h3>
              <label className="block text-xs">
                SEO title
                <input
                  maxLength={70}
                  name="seo_title"
                  {...form.attributes("seo_title")}
                  value={seoTitle}
                  onChange={(e) => setSeoTitle(e.target.value)}
                  placeholder="Uses the content title if empty"
                  className="auth-input"
                />
                <FieldError state={form} name="seo_title" />
                <span className="mt-1 block text-[10px] text-gray-400">
                  {seoTitle.length}/70
                </span>
              </label>
              <label className="block text-xs">
                Meta description
                <textarea
                  maxLength={170}
                  rows={4}
                  name="seo_description"
                  {...form.attributes("seo_description")}
                  value={seoDescription}
                  onChange={(e) => setSeoDescription(e.target.value)}
                  placeholder="Uses the excerpt or content if empty"
                  className="auth-input resize-y"
                />
                <FieldError state={form} name="seo_description" />
                <span className="mt-1 block text-[10px] text-gray-400">
                  {seoDescription.length}/170
                </span>
              </label>
              <label className="flex items-start gap-2 text-xs leading-5">
                <input
                  name="seo_noindex"
                  type="checkbox"
                  checked={seoNoindex}
                  onChange={(e) => setSeoNoindex(e.target.checked)}
                  className="mt-1"
                />
                Hide from search results (noindex)
              </label>
              <p className="text-[10px] leading-5 text-gray-400">
                The featured image is used for social sharing. Canonical URLs
                are generated from the slug. Noindex is not a privacy setting.
              </p>
            </section>
            {(profile === "event" || profile === "property") && (
              <section className="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                <h3 className="text-sm font-semibold">
                  {profile === "event" ? "Event" : "Property"} details
                </h3>
                <label className="block text-xs">
                  Location
                  <input
                    required={status !== "draft"}
                    maxLength={300}
                    name="details.location"
                    {...form.attributes("details.location")}
                    value={location}
                    onChange={(e) => setLocation(e.target.value)}
                    className="auth-input"
                  />
                  <FieldError state={form} name="details.location" />
                </label>
                {profile === "event" ? (
                  <>
                    {[
                      {
                        name: "details.starts_at",
                        label: "Starts",
                        value: starts,
                        set: setStarts,
                      },
                      {
                        name: "details.ends_at",
                        label: "Ends (optional)",
                        value: ends,
                        set: setEnds,
                      },
                    ].map((field) => (
                      <label key={field.label} className="block text-xs">
                        {field.label}
                        <input
                          type="datetime-local"
                          required={
                            field.label === "Starts" && status !== "draft"
                          }
                          name={field.name}
                          {...form.attributes(field.name)}
                          value={field.value}
                          onChange={(e) => field.set(e.target.value)}
                          className="auth-input"
                        />
                        <FieldError state={form} name={field.name} />
                      </label>
                    ))}
                    <p className="text-[10px] text-gray-400">
                      Times use{" "}
                      {Intl.DateTimeFormat().resolvedOptions().timeZone}.
                    </p>
                  </>
                ) : (
                  <>
                    <label className="block text-xs">
                      Price
                      <input
                        required
                        type="number"
                        min="0"
                        step="0.01"
                        name="details.price"
                        {...form.attributes("details.price")}
                        value={price}
                        onChange={(e) => setPrice(e.target.value)}
                        className="auth-input"
                      />
                      <FieldError state={form} name="details.price" />
                    </label>
                    <label className="block text-xs">
                      Currency
                      <input
                        required
                        maxLength={3}
                        pattern="[A-Z]{3}"
                        name="details.currency"
                        {...form.attributes("details.currency")}
                        value={currency}
                        onChange={(e) =>
                          setCurrency(e.target.value.toUpperCase())
                        }
                        className="auth-input"
                      />
                      <FieldError state={form} name="details.currency" />
                    </label>
                    <label className="block text-xs">
                      Listing type
                      <select
                        name="details.listing_type"
                        {...form.attributes("details.listing_type")}
                        value={listingType}
                        onChange={(e) =>
                          setListingType(e.target.value as "sale" | "rent")
                        }
                        className="auth-input"
                      >
                        <option value="sale">For sale</option>
                        <option value="rent">For rent</option>
                      </select>
                      <FieldError state={form} name="details.listing_type" />
                    </label>
                    <label className="block text-xs">
                      Availability
                      <select
                        name="details.availability"
                        {...form.attributes("details.availability")}
                        value={availability}
                        onChange={(e) =>
                          setAvailability(e.target.value as typeof availability)
                        }
                        className="auth-input"
                      >
                        <option value="available">Available</option>
                        <option value="under_offer">Under offer</option>
                        <option value="sold">Sold</option>
                        <option value="rented">Rented</option>
                      </select>
                      <FieldError state={form} name="details.availability" />
                    </label>
                    {[
                      {
                        name: "details.bedrooms",
                        label: "Bedrooms",
                        value: bedrooms,
                        set: setBedrooms,
                      },
                      {
                        name: "details.bathrooms",
                        label: "Bathrooms",
                        value: bathrooms,
                        set: setBathrooms,
                      },
                    ].map((field) => (
                      <label key={field.label} className="block text-xs">
                        {field.label}
                        <input
                          type="number"
                          required
                          min={0}
                          max={100}
                          step={1}
                          name={field.name}
                          {...form.attributes(field.name)}
                          value={field.value}
                          onChange={(e) => field.set(Number(e.target.value))}
                          className="auth-input"
                        />
                        <FieldError state={form} name={field.name} />
                      </label>
                    ))}
                  </>
                )}
              </section>
            )}
            {profile === "property" && (
              <section className="rounded-xl border border-gray-200 bg-white p-5">
                <MediaField
                  multiple
                  label="Property gallery"
                  ids={gallery}
                  onChange={(ids) => {
                    form.clear("details.gallery_ids");
                    setGallery(ids);
                    change();
                  }}
                />
                <FieldError state={form} name="details.gallery_ids" />
              </section>
            )}
          </aside>
        </div>
      </Form>
      {blocker.state === "blocked" && (
        <AdminModal
          title="Leave without saving?"
          onClose={() => blocker.reset()}
        >
          <p className="text-sm text-gray-600">
            Your unsaved changes will be lost.
          </p>
          <div className="mt-6 flex justify-end gap-3">
            <button
              onClick={() => blocker.reset()}
              className="rounded-lg border px-4 py-2 text-sm"
            >
              Keep editing
            </button>
            <button
              onClick={() => blocker.proceed()}
              className="rounded-lg bg-gray-800 px-4 py-2 text-sm text-white"
            >
              Discard changes
            </button>
          </div>
        </AdminModal>
      )}
    </>
  );
}
