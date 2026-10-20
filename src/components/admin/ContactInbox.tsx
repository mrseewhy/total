import { useEffect, useState } from "react";
import { Mail, MailOpen, ChevronLeft, ChevronRight } from "lucide-react";
import { fetchContacts, setContactRead } from "../../api/contacts";
import type { ContactPage } from "../../api/contacts";

export default function ContactInbox() {
  const [page, setPage] = useState(1);
  const [result, setResult] = useState<ContactPage | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [retry, setRetry] = useState(0);
  const [selected, setSelected] = useState<number | null>(null);
  const [updating, setUpdating] = useState(false);
  useEffect(() => {
    const abort = new AbortController();
    fetchContacts(page, abort.signal)
      .then(setResult)
      .catch((e) => {
        if (!abort.signal.aborted) setError(e.message);
      })
      .finally(() => {
        if (!abort.signal.aborted) setLoading(false);
      });
    return () => abort.abort();
  }, [page, retry]);
  const inquiry = result?.data.find((item) => item.id === selected);
  async function changeRead(id: number, read: boolean) {
    setUpdating(true);
    setError("");
    try {
      const updated = await setContactRead(id, read);
      window.dispatchEvent(new Event("contacts-updated"));
      setResult((previous) =>
        previous
          ? {
              ...previous,
              data: previous.data.map((item) =>
                item.id === id ? updated : item,
              ),
            }
          : previous,
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not update inquiry");
    } finally {
      setUpdating(false);
    }
  }
  function go(next: number) {
    setPage(next);
    setLoading(true);
    setSelected(null);
    setError("");
  }
  return (
    <section
      className="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white"
      aria-busy={loading}
    >
      <div className="border-b border-gray-100 px-6 py-5">
        <h2 className="font-semibold">Contact inquiries</h2>
        <p className="mt-1 text-xs text-gray-500">
          {result?.pagination.total ?? 0} inquiries
        </p>
      </div>
      {error && (
        <div
          role="alert"
          className="m-5 rounded bg-red-50 p-4 text-sm text-red-700"
        >
          {error}
          <button
            className="ml-3 underline"
            onClick={() => {
              setError("");
              setLoading(true);
              setRetry(retry + 1);
            }}
          >
            Retry
          </button>
        </div>
      )}
      {loading ? (
        <p role="status" className="p-8 text-sm text-gray-500">
          Loading inquiries…
        </p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-gray-50 text-xs text-gray-500">
              <tr>
                {["Sender", "Interest", "Status", "Submitted", ""].map(
                  (label, index) => (
                    <th key={index} className="px-6 py-3 font-medium">
                      {label || <span className="sr-only">Action</span>}
                    </th>
                  ),
                )}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {result?.data.map((item) => (
                <tr
                  key={item.id}
                  className={item.is_read ? "" : "bg-green-50/40"}
                >
                  <td className="px-6 py-4">
                    <p className={item.is_read ? "" : "font-semibold"}>
                      {item.name}
                    </p>
                    <p className="mt-1 text-xs text-gray-500">{item.email}</p>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {item.interest}
                  </td>
                  <td className="px-6 py-4">
                    <span
                      className={`rounded-full px-2 py-1 text-xs ${item.is_read ? "bg-gray-100 text-gray-500" : "bg-green-100 text-green-800"}`}
                    >
                      {item.is_read ? "Read" : "Unread"}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                    {new Date(
                      item.created_at.replace(" ", "T") + "Z",
                    ).toLocaleString("en-GB")}
                  </td>
                  <td className="px-6 py-4">
                    <button
                      disabled={updating}
                      className="text-xs font-medium underline"
                      onClick={() => {
                        setSelected(item.id);
                        if (!item.is_read) void changeRead(item.id, true);
                      }}
                    >
                      View
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {result?.data.length === 0 && (
            <p className="p-12 text-center text-sm text-gray-500">
              No inquiries yet.
            </p>
          )}
        </div>
      )}
      {inquiry && (
        <article className="m-5 rounded-xl border border-gray-200 p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h3 className="text-lg font-semibold">{inquiry.name}</h3>
              <a
                href={`mailto:${inquiry.email}`}
                className="mt-1 block text-sm text-green-800"
              >
                {inquiry.email}
              </a>
              {inquiry.phone && (
                <p className="mt-1 text-sm text-gray-500">{inquiry.phone}</p>
              )}
            </div>
            <button
              onClick={() => setSelected(null)}
              className="text-xs underline"
            >
              Close inquiry
            </button>
          </div>
          <p className="mt-5 text-xs font-medium text-gray-500">
            {inquiry.interest}
          </p>
          <p className="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-gray-700">
            {inquiry.message}
          </p>
          <button
            disabled={updating}
            onClick={() => void changeRead(inquiry.id, !inquiry.is_read)}
            className="mt-6 flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs disabled:opacity-50"
          >
            {inquiry.is_read ? (
              <Mail className="size-4" />
            ) : (
              <MailOpen className="size-4" />
            )}
            {updating
              ? "Saving…"
              : inquiry.is_read
                ? "Mark as unread"
                : "Mark as read"}
          </button>
        </article>
      )}
      {result && (
        <div className="flex items-center justify-between border-t border-gray-100 px-6 py-4 text-xs text-gray-500">
          <span>
            Page {result.pagination.page} of {result.pagination.pages}
          </span>
          <div className="flex gap-3">
            <button
              aria-label="Previous page"
              disabled={loading || result.pagination.page === 1}
              onClick={() => go(result.pagination.page - 1)}
              className="rounded border p-2 disabled:opacity-30"
            >
              <ChevronLeft className="size-4" />
            </button>
            <button
              aria-label="Next page"
              disabled={
                loading || result.pagination.page === result.pagination.pages
              }
              onClick={() => go(result.pagination.page + 1)}
              className="rounded border p-2 disabled:opacity-30"
            >
              <ChevronRight className="size-4" />
            </button>
          </div>
        </div>
      )}
    </section>
  );
}
