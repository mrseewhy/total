import { useEffect, useState } from "react";
import { getMedia } from "../../../api/content";
import type { MediaItem } from "../../../api/content";
import MediaPicker from "../media/MediaPicker";
export default function MediaField({
  ids,
  onChange,
  multiple = false,
  label,
}: {
  ids: number[];
  onChange: (ids: number[]) => void;
  multiple?: boolean;
  label: string;
}) {
  const [picker, setPicker] = useState(false);
  const [images, setImages] = useState<MediaItem[]>([]);
  const [error, setError] = useState("");
  const key = ids.join(",");
  useEffect(() => {
    const abort = new AbortController();
    const values = key ? key.split(",").map(Number) : [];
    Promise.all(values.map((id) => getMedia(id, abort.signal)))
      .then((images) => {
        if (!abort.signal.aborted) {
          setImages(images);
          setError("");
        }
      })
      .catch((e) => {
        if (!abort.signal.aborted) setError(e.message);
      });
    return () => abort.abort();
  }, [key]);
  return (
    <div>
      <p className="text-xs font-medium">{label}</p>
      <div className="mt-3 grid grid-cols-3 gap-2">
        {images
          .filter((image) => ids.includes(image.id))
          .map((image) => (
            <div key={image.id} className="relative">
              <img
                src={image.url}
                alt={image.alt_text || image.title}
                className="aspect-square w-full rounded-lg object-cover"
              />
              <button
                type="button"
                aria-label={`Remove ${image.title}`}
                onClick={() => onChange(ids.filter((id) => id !== image.id))}
                className="absolute top-1 right-1 rounded-full bg-white px-1.5 text-xs shadow"
              >
                ×
              </button>
            </div>
          ))}
      </div>
      {error && <p className="mt-2 text-xs text-red-600">{error}</p>}
      <button
        type="button"
        onClick={() => setPicker(true)}
        className="mt-3 rounded-lg border border-gray-200 px-3 py-2 text-xs"
      >
        {ids.length ? "Change" : "Choose"} {multiple ? "images" : "image"}
      </button>
      {picker && (
        <MediaPicker
          multiple={multiple}
          onClose={() => setPicker(false)}
          onChoose={(items) =>
            onChange(
              multiple
                ? Array.from(new Set([...ids, ...items.map((item) => item.id)]))
                : items.map((item) => item.id),
            )
          }
        />
      )}
    </div>
  );
}
