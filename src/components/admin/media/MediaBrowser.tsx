import { useForm } from "../../../forms/useForm";
import { FormFeedback } from "../../forms/Form";
import { useEffect, useState } from "react";
import { Upload, Check, ImagePlus, Search } from "lucide-react";
import { listMedia, uploadMedia } from "../../../api/content";
import type { MediaItem, Paging } from "../../../api/content";
import Pagination from "../Pagination";
export default function MediaBrowser({
  onOpen,
  selected = [],
  onSelect,
  refreshKey = 0,
}: {
  onOpen?: (media: MediaItem) => void;
  selected?: number[];
  onSelect?: (media: MediaItem) => void;
  refreshKey?: number;
}) {
  const [data, setData] = useState<{
    data: MediaItem[];
    pagination: Paging;
  } | null>(null);
  const [unused, setUnused] = useState(false);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [version, setVersion] = useState(0);
  const [busy, setBusy] = useState(true);
  const uploadForm = useForm();
  const uploading = uploadForm.busy;
  const [error, setError] = useState("");
  useEffect(() => {
    const abort = new AbortController();
    listMedia(page, query, abort.signal, unused)
      .then(setData)
      .catch((e) => {
        if (!abort.signal.aborted) setError(e.message);
      })
      .finally(() => {
        if (!abort.signal.aborted) setBusy(false);
      });
    return () => abort.abort();
  }, [page, query, version, refreshKey, unused]);
  async function upload(files: FileList | null) {
    if (!files) return;
    let uploaded = 0;
    await uploadForm.submit(
      async () => {
        if (files.length > 10)
          throw new Error("Upload at most 10 images at a time.");
        try {
          for (const file of Array.from(files)) {
            await uploadMedia(file);
            uploaded++;
          }
        } finally {
          if (uploaded > 0) {
            setPage(1);
            setBusy(true);
            setVersion((v) => v + 1);
          }
        }
        return uploaded;
      },
      (count) => `${count} image${count === 1 ? "" : "s"} uploaded.`,
    );
  }

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-5">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            setBusy(true);
            setError("");
            setPage(1);
            setQuery(search);
            setVersion((v) => v + 1);
          }}
          className="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2"
        >
          <Search className="size-4 text-gray-400" />
          <input
            aria-label="Search media"
            maxLength={200}
            placeholder="Search images…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-36 text-xs outline-none"
          />
          <button className="text-xs text-gray-500">Search</button>
        </form>
        <select
          aria-label="Filter image usage"
          value={unused ? "unused" : "all"}
          onChange={(e) => {
            setUnused(e.target.value === "unused");
            setPage(1);
            setBusy(true);
          }}
          className="rounded-lg border border-gray-200 px-3 py-2 text-xs"
        >
          <option value="all">All images</option>
          <option value="unused">Unattached images</option>
        </select>
        <label
          className={`flex cursor-pointer items-center gap-2 rounded-lg bg-gray-800 px-4 py-2.5 text-xs text-white ${uploading ? "opacity-50" : ""}`}
        >
          <Upload className="size-4" />
          {uploading ? "Uploading…" : "Upload images"}
          <input
            aria-label="Upload images"
            type="file"
            multiple
            accept="image/jpeg,image/png,image/webp,image/gif"
            disabled={uploading}
            className="sr-only"
            onChange={(e) => {
              void upload(e.target.files);
              e.target.value = "";
            }}
          />
        </label>
      </div>
      <p className="px-5 pt-3 text-[11px] text-gray-400">
        JPEG, PNG, WebP or GIF · Up to 8 MB each
      </p>
      <div className="px-5">
        <FormFeedback state={uploadForm} />
      </div>
      {error && (
        <p
          role="alert"
          className="m-5 rounded-lg bg-red-50 p-4 text-sm text-red-600"
        >
          {error}
          <button
            onClick={() => {
              setError("");
              setBusy(true);
              setVersion((v) => v + 1);
            }}
            className="ml-3 underline"
          >
            Retry
          </button>
        </p>
      )}
      {busy ? (
        <p role="status" className="p-8 text-sm text-gray-500">
          Loading media…
        </p>
      ) : (
        <div className="grid grid-cols-2 gap-4 p-5 sm:grid-cols-3 xl:grid-cols-4">
          {data?.data.map((media) => (
            <article
              key={media.id}
              className={`relative overflow-hidden rounded-xl border ${selected.includes(media.id) ? "border-green-600 ring-2 ring-green-600" : "border-gray-200"}`}
            >
              <button
                type="button"
                onClick={() => (onOpen ? onOpen(media) : onSelect?.(media))}
                className="block w-full text-left"
              >
                <img
                  src={media.url}
                  alt={media.alt_text || media.title}
                  loading="lazy"
                  className="aspect-square w-full bg-gray-100 object-cover"
                />
                <div className="p-3">
                  <p className="truncate text-xs font-medium">{media.title}</p>
                  <p className="mt-1 text-[10px] text-gray-400">
                    {media.used_by.length ? "In use" : "Unattached"} ·{" "}
                    {media.width} × {media.height} ·{" "}
                    {(media.size_bytes / 1024).toFixed(0)} KB
                  </p>
                </div>
              </button>
              {onSelect && (
                <button
                  type="button"
                  aria-label={`Select ${media.title}`}
                  aria-pressed={selected.includes(media.id)}
                  onClick={() => onSelect(media)}
                  className={`absolute top-2 left-2 flex size-6 items-center justify-center rounded border shadow-sm ${selected.includes(media.id) ? "border-green-700 bg-green-700 text-white" : "border-gray-300 bg-white"}`}
                >
                  {selected.includes(media.id) && <Check className="size-4" />}
                </button>
              )}
            </article>
          ))}
        </div>
      )}
      {!busy && !data?.data.length && (
        <div className="p-12 text-center">
          <ImagePlus className="mx-auto size-9 text-gray-300" />
          <p className="mt-4 text-sm text-gray-500">
            No images match this view.
          </p>
        </div>
      )}
      {data && (
        <Pagination
          value={data.pagination}
          busy={busy}
          onChange={(page) => {
            setPage(page);
            setBusy(true);
            setError("");
          }}
        />
      )}
    </div>
  );
}
