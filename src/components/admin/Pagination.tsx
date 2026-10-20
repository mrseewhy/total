import type { Paging } from "../../api/content";
export default function Pagination({
  value,
  busy,
  onChange,
}: {
  value: Paging;
  busy: boolean;
  onChange: (page: number) => void;
}) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-4 text-xs text-gray-500">
      <span>
        {value.total} items · Page {value.page} of {value.pages}
      </span>
      <nav aria-label="Pagination" className="flex gap-2">
        <button
          disabled={busy || value.page <= 1}
          onClick={() => onChange(value.page - 1)}
          className="rounded border border-gray-200 px-3 py-2 disabled:opacity-30"
        >
          Previous
        </button>
        <button
          disabled={busy || value.page >= value.pages}
          onClick={() => onChange(value.page + 1)}
          className="rounded border border-gray-200 px-3 py-2 disabled:opacity-30"
        >
          Next
        </button>
      </nav>
    </div>
  );
}
