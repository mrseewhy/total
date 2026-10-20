import { contentModule } from "../../../content/modules";
import { lazy, Suspense, useEffect, useState } from "react";
import { Link } from "react-router";
import type { ContentItem, ContentKind, MediaItem } from "../../../api/content";
import { getMedia, formatDate } from "../../../api/content";
const RichTextEditor = lazy(() => import("./RichTextEditor"));
export default function ContentPreview({
  item,
  kind,
}: {
  item: ContentItem;
  kind: ContentKind;
}) {
  const [images, setImages] = useState<MediaItem[]>([]);
  useEffect(() => {
    const abort = new AbortController();
    const ids = Array.from(
      new Set([
        ...(item.featured_media_id ? [item.featured_media_id] : []),
        ...(item.details.gallery_ids ?? []),
      ]),
    );
    Promise.all(ids.map((id) => getMedia(id, abort.signal)))
      .then(setImages)
      .catch(() => {});
    return () => abort.abort();
  }, [item.featured_media_id, item.details.gallery_ids]);
  return (
    <section className="mt-6">
      <div className="mb-5 flex flex-wrap justify-between gap-4 text-xs">
        <Link to={`/site-admin/${kind}`} className="text-gray-500">
          ← All {kind}
        </Link>
        <Link
          to={`/site-admin/${kind}/${item.id}/edit`}
          className="font-medium"
        >
          Edit content →
        </Link>
      </div>
      <article className="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <div className="border-b border-gray-100 bg-gray-50 p-4 text-xs text-gray-500">
          Private preview · {item.effective_status}
          {item.effective_status === "scheduled"
            ? ` · Publishes ${formatDate(item.publish_at)}`
            : ""}
        </div>
        {images[0] && (
          <img
            src={images[0].url}
            alt={images[0].alt_text}
            className="max-h-96 w-full object-cover"
          />
        )}
        <div className="px-6 py-8 sm:px-10">
          <h1 className="text-3xl font-semibold tracking-tight">
            {item.title}
          </h1>
          {item.excerpt && (
            <p className="mt-5 text-lg leading-8 text-gray-500">
              {item.excerpt}
            </p>
          )}
          {contentModule(kind).profile === "event" && (
            <div className="mt-6 rounded-lg bg-gray-50 p-4 text-sm text-gray-600">
              <p>{item.details.location || "Location to be confirmed"}</p>
              <p className="mt-2">
                {formatDate(item.details.starts_at ?? null)}
                {item.details.ends_at
                  ? ` – ${formatDate(item.details.ends_at)}`
                  : ""}
              </p>
            </div>
          )}
          {contentModule(kind).profile === "property" && (
            <div className="mt-6 flex flex-wrap gap-5 rounded-lg bg-gray-50 p-4 text-sm text-gray-600">
              <strong>
                {item.details.currency}{" "}
                {Number(item.details.price).toLocaleString()}
              </strong>
              <span>For {item.details.listing_type}</span>
              <span>{item.details.location}</span>
              <span>
                {item.details.bedrooms} beds · {item.details.bathrooms} baths
              </span>
              <span className="capitalize">
                {item.details.availability?.replace("_", " ")}
              </span>
            </div>
          )}
        </div>
        <Suspense fallback={<p className="p-8">Loading preview…</p>}>
          <RichTextEditor readOnly initialContent={item.content} />
        </Suspense>
        {images.length > 1 && (
          <div className="grid grid-cols-2 gap-4 p-6 sm:grid-cols-3">
            {images.slice(1).map((image) => (
              <img
                key={image.id}
                src={image.url}
                alt={image.alt_text}
                className="aspect-square w-full rounded-xl object-cover"
              />
            ))}
          </div>
        )}
      </article>
    </section>
  );
}
