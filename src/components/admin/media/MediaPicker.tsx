import { useState } from "react";
import AdminModal from "../AdminModal";
import MediaBrowser from "./MediaBrowser";
import type { MediaItem } from "../../../api/content";
export default function MediaPicker({
  multiple = false,
  onChoose,
  onClose,
}: {
  multiple?: boolean;
  onChoose: (media: MediaItem[]) => void;
  onClose: () => void;
}) {
  const [selected, setSelected] = useState<MediaItem[]>([]);
  return (
    <AdminModal wide title="Choose media" onClose={onClose}>
      <MediaBrowser
        selected={selected.map((m) => m.id)}
        onSelect={(media) =>
          setSelected((previous) =>
            previous.some((m) => m.id === media.id)
              ? previous.filter((m) => m.id !== media.id)
              : multiple
                ? [...previous, media]
                : [media],
          )
        }
      />
      <div className="sticky bottom-0 mt-4 flex justify-end border-t border-gray-100 bg-white pt-4">
        <button
          disabled={!selected.length}
          onClick={() => {
            onChoose(selected);
            onClose();
          }}
          className="rounded-lg bg-gray-800 px-4 py-2 text-sm text-white disabled:opacity-40"
        >
          Use {selected.length || ""} {multiple ? "images" : "image"}
        </button>
      </div>
    </AdminModal>
  );
}
