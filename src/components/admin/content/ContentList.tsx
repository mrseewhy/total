import { toast } from "sonner";
import { contentModule } from "../../../content/modules";
import { useEffect, useState } from "react";
import { Link } from "react-router";
import { Plus, Pencil, Eye, Trash2 } from "lucide-react";
import { listContent, cmsRequest, formatDate } from "../../../api/content";
import type { ContentKind, ContentItem, Paging } from "../../../api/content";
import Pagination from "../Pagination";
import ConfirmDelete from "../ConfirmDelete";
export default function ContentList({ kind }: { kind: ContentKind }) {
  const module = contentModule(kind);
  const [result, setResult] = useState<{
    data: ContentItem[];
    pagination: Paging;
  } | null>(null);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [query, setQuery] = useState("");
  const [status, setStatus] = useState("");
  const [busy, setBusy] = useState(true);
  const [error, setError] = useState("");
  const [version, setVersion] = useState(0);
  const [selected, setSelected] = useState<number[]>([]);
  const [deleting, setDeleting] = useState<number[] | null>(null);
  useEffect(() => {
    const abort = new AbortController();
    listContent(kind, page, query, status, abort.signal)
      .then(setResult)
      .catch((e) => {
        if (!abort.signal.aborted) setError(e.message);
      })
      .finally(() => {
        if (!abort.signal.aborted) setBusy(false);
      });
    return () => abort.abort();
  }, [kind, page, query, status, version]);
  const reload = () => {
    setBusy(true);
    setError("");
    setSelected([]);
    setVersion((v) => v + 1);
  };
  function changePage(page: number) {
    setPage(page);
    setBusy(true);
    setSelected([]);
    setError("");
  }
  return (
    <section className="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white">
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 p-5">
        <div>
          <h2 className="font-semibold">{module.label}</h2>
          <p className="mt-1 text-xs text-gray-500">
            {result?.pagination.total ?? 0} items
          </p>
        </div>
        <Link
          to={`/site-admin/${kind}/new`}
          className="flex items-center gap-2 rounded-lg bg-gray-800 px-4 py-2.5 text-xs text-white"
        >
          <Plus className="size-4" />
          Create {module.singular}
        </Link>
      </div>
      <div className="flex flex-wrap items-center gap-3 p-5">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            setQuery(search);
            setPage(1);
            reload();
          }}
          className="flex gap-2"
        >
          <input
            aria-label="Search content"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            maxLength={200}
            placeholder="Search titles…"
            className="rounded-lg border border-gray-200 px-3 py-2 text-xs"
          />
          <button className="rounded-lg border border-gray-200 px-3 text-xs">
            Search
          </button>
        </form>
        <select
          aria-label="Filter status"
          value={status}
          onChange={(e) => {
            setStatus(e.target.value);
            setPage(1);
            reload();
          }}
          className="rounded-lg border border-gray-200 px-3 py-2 text-xs"
        >
          <option value="">All statuses</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          {module.scheduling && <option value="scheduled">Scheduled</option>}
        </select>
        {selected.length > 0 && (
          <button
            onClick={() => setDeleting(selected)}
            className="ml-auto text-xs text-red-600"
          >
            Delete selected ({selected.length})
          </button>
        )}
      </div>
      {error && (
        <p
          role="alert"
          className="mx-5 mb-5 rounded-lg bg-red-50 p-4 text-sm text-red-600"
        >
          {error}
          <button onClick={reload} className="ml-3 underline">
            Retry
          </button>
        </p>
      )}
      {busy ? (
        <p role="status" className="p-8 text-sm text-gray-500">
          Loading {kind}…
        </p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-gray-50 text-xs text-gray-500">
              <tr>
                <th className="px-5 py-3">
                  <input
                    type="checkbox"
                    aria-label="Select all on this page"
                    checked={
                      !!result?.data.length &&
                      result.data.every((item) => selected.includes(item.id))
                    }
                    onChange={(e) =>
                      setSelected(
                        e.target.checked
                          ? (result?.data.map((item) => item.id) ?? [])
                          : [],
                      )
                    }
                  />
                </th>
                <th className="py-3 pr-5 font-medium">Title</th>
                <th className="px-5 py-3 font-medium">Status</th>
                <th className="px-5 py-3 font-medium">
                  {module.scheduling ? "Publication" : "Updated"}
                </th>
                <th className="px-5 py-3 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {result?.data.map((item) => (
                <tr key={item.id}>
                  <td className="px-5 py-4">
                    <input
                      type="checkbox"
                      aria-label={`Select ${item.title}`}
                      checked={selected.includes(item.id)}
                      onChange={(e) =>
                        setSelected((previous) =>
                          e.target.checked
                            ? [...previous, item.id]
                            : previous.filter((id) => id !== item.id),
                        )
                      }
                    />
                  </td>
                  <td className="py-4 pr-5">
                    <Link
                      to={`/site-admin/${kind}/${item.id}/edit`}
                      className="font-medium hover:underline"
                    >
                      {item.title}
                    </Link>
                    <p className="mt-1 text-xs text-gray-400">/{item.slug}</p>
                  </td>
                  <td className="px-5 py-4">
                    <span
                      className={`rounded-full px-2 py-1 text-xs capitalize ${item.effective_status === "published" ? "bg-green-50 text-green-700" : item.effective_status === "scheduled" ? "bg-blue-50 text-blue-700" : "bg-amber-50 text-amber-700"}`}
                    >
                      {item.effective_status}
                    </span>
                  </td>
                  <td className="whitespace-nowrap px-5 py-4 text-xs text-gray-500">
                    {formatDate(
                      module.scheduling ? item.publish_at : item.updated_at,
                    )}
                  </td>
                  <td className="px-5 py-4">
                    <div className="flex gap-3">
                      <Link
                        aria-label={`Preview ${item.title}`}
                        to={`/site-admin/${kind}/${item.id}/preview`}
                      >
                        <Eye className="size-4" />
                      </Link>
                      <Link
                        aria-label={`Edit ${item.title}`}
                        to={`/site-admin/${kind}/${item.id}/edit`}
                      >
                        <Pencil className="size-4" />
                      </Link>
                      <button
                        aria-label={`Delete ${item.title}`}
                        onClick={() => setDeleting([item.id])}
                        className="text-red-600"
                      >
                        <Trash2 className="size-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {!result?.data.length && (
            <p className="p-12 text-center text-sm text-gray-500">
              No {kind} found.
            </p>
          )}
        </div>
      )}
      {result && (
        <Pagination
          value={result.pagination}
          busy={busy}
          onChange={changePage}
        />
      )}{" "}
      {deleting && (
        <ConfirmDelete
          title={`Delete ${deleting.length} item${deleting.length === 1 ? "" : "s"}?`}
          description="Selected content will be permanently deleted. Attached images will remain in the media library."
          onClose={() => setDeleting(null)}
          onConfirm={async () => {
            await cmsRequest(`${kind}/bulk-delete`, "POST", { ids: deleting });
            toast.success("Content deleted.");
            reload();
          }}
        />
      )}
    </section>
  );
}
