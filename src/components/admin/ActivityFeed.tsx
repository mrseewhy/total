import { contentModules } from "../../content/modules";
import { useEffect, useState } from "react";
import { Link } from "react-router";
import { Activity } from "lucide-react";
import { cmsRequest, type Paging } from "../../api/content";
import { useAuth } from "../../auth/context";
import Pagination from "./Pagination";
type Entry = {
  id: number;
  actor_name: string;
  module: string;
  action: string;
  target_id: string;
  summary: string;
  created_at: string;
};
export default function ActivityFeed({
  compact = false,
}: {
  compact?: boolean;
}) {
  const { user } = useAuth();
  const [result, setResult] = useState<{
    data: Entry[];
    pagination: Paging;
  } | null>(null);
  const [page, setPage] = useState(1);
  const [module, setModule] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    cmsRequest(
      `activity?page=${page}&module=${encodeURIComponent(module)}`,
      "GET",
      undefined,
      controller.signal,
    )
      .then((data) => {
        setResult(data);
        setError("");
      })
      .catch((e) => {
        if (!controller.signal.aborted)
          setError(e instanceof Error ? e.message : "Could not load activity.");
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [page, module, revision]);
  return (
    <section className="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white">
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 p-5">
        <div>
          <h2 className="flex items-center gap-2 font-semibold">
            <Activity className="size-5" aria-hidden="true" />
            {compact ? "Recent activity" : "Activity feed"}
          </h2>
          <p className="mt-1 text-xs text-gray-500">
            Changes and account activity, newest first.
          </p>
        </div>
        {compact ? (
          <Link
            to="/site-admin/activity"
            className="text-sm font-medium text-green-800"
          >
            View all activity →
          </Link>
        ) : (
          <div className="flex gap-3">
            <label className="sr-only" htmlFor="activity-module">
              Filter activity
            </label>
            <select
              id="activity-module"
              value={module}
              onChange={(event) => {
                setModule(event.target.value);
                setPage(1);
                setLoading(true);
              }}
              className="rounded-lg border border-gray-300 px-3 py-2 text-sm"
            >
              <option value="">All activity</option>
              {[
                ...contentModules.map((module) => module.id),
                "media",
                "seo",
                "contacts",
                "profile",
                "auth",
                ...(user?.role === "Admin" ? ["users"] : []),
              ].map((value) => (
                <option key={value} value={value}>
                  {value === "seo"
                    ? "SEO"
                    : value.charAt(0).toUpperCase() + value.slice(1)}
                </option>
              ))}
            </select>
            <button
              disabled={loading}
              onClick={() => {
                setLoading(true);
                setRevision((value) => value + 1);
              }}
              className="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50"
            >
              Refresh
            </button>
          </div>
        )}
      </div>
      {error && (
        <div className="p-5 text-sm text-red-600" role="alert">
          {error}
          <button
            className="ml-3 underline"
            onClick={() => {
              setLoading(true);
              setRevision((value) => value + 1);
            }}
          >
            Retry
          </button>
        </div>
      )}
      {loading && (
        <p role="status" className="p-5 text-sm text-gray-500">
          Loading activity…
        </p>
      )}
      {!loading && !error && result && (
        <>
          {result.data.length === 0 ? (
            <p className="p-8 text-sm text-gray-500">
              No activity recorded yet.
            </p>
          ) : (
            <ul className="divide-y divide-gray-100">
              {(compact ? result.data.slice(0, 5) : result.data).map(
                (entry) => (
                  <li
                    key={entry.id}
                    className="flex flex-wrap justify-between gap-3 px-5 py-4"
                  >
                    <div className="min-w-0">
                      <p className="break-words text-sm font-medium">
                        {entry.summary}
                      </p>
                      <p className="mt-1 text-xs text-gray-500">
                        {entry.actor_name} ·{" "}
                        {entry.module === "seo" ? "SEO" : entry.module} ·{" "}
                        {entry.action.replaceAll("_", " ")}
                      </p>
                    </div>
                    <time
                      dateTime={entry.created_at.replace(" ", "T") + "Z"}
                      className="text-xs text-gray-400"
                    >
                      {new Date(
                        entry.created_at.replace(" ", "T") + "Z",
                      ).toLocaleString()}
                    </time>
                  </li>
                ),
              )}
            </ul>
          )}
          {!compact && (
            <Pagination
              value={result.pagination}
              busy={loading}
              onChange={(value) => {
                setPage(value);
                setLoading(true);
              }}
            />
          )}
        </>
      )}
    </section>
  );
}
