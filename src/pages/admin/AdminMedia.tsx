import { toast } from "sonner";
import { useState } from "react";
import MediaBrowser from "../../components/admin/media/MediaBrowser";
import MediaDetails from "../../components/admin/media/MediaDetails";
import ConfirmDelete from "../../components/admin/ConfirmDelete";
import { cmsRequest } from "../../api/content";
import type { MediaItem } from "../../api/content";
export default function AdminMedia() {
  const [opened, setOpened] = useState<MediaItem | null>(null);
  const [selected, setSelected] = useState<number[]>([]);
  const [version, setVersion] = useState(0);
  const [deleting, setDeleting] = useState(false);
  const refresh = () => setVersion((v) => v + 1);
  return (
    <section className="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-5">
        <h2 className="font-semibold">Media library</h2>
        <div className="flex items-center gap-4 text-xs text-gray-500">
          <span>{selected.length} selected</span>
          {selected.length > 0 && (
            <>
              <button onClick={() => setSelected([])}>Clear selection</button>
              <button
                onClick={() => setDeleting(true)}
                className="text-red-600"
              >
                Delete selected
              </button>
            </>
          )}
        </div>
      </div>
      <MediaBrowser
        refreshKey={version}
        selected={selected}
        onOpen={setOpened}
        onSelect={(media) =>
          setSelected((previous) =>
            previous.includes(media.id)
              ? previous.filter((id) => id !== media.id)
              : [...previous, media.id],
          )
        }
      />
      {opened && (
        <MediaDetails
          media={opened}
          onClose={() => setOpened(null)}
          onChanged={refresh}
        />
      )}{" "}
      {deleting && (
        <ConfirmDelete
          title={`Delete ${selected.length} images?`}
          description="Selected files will be permanently deleted. Images used by saved content cannot be deleted."
          onClose={() => setDeleting(false)}
          onConfirm={async () => {
            await cmsRequest("media/bulk-delete", "POST", { ids: selected });
            toast.success("Images deleted.");
            setSelected([]);
            refresh();
          }}
        />
      )}
    </section>
  );
}
