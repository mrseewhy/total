import { useEffect, useState } from "react";
import { getMetadata, type MetadataResponse } from "../../api/metadata";
import MetadataForm from "../../components/admin/MetadataForm";
export default function AdminSeo() {
  const [data, setData] = useState<MetadataResponse | null>(null);
  const [path, setPath] = useState("/");
  const [error, setError] = useState("");
  const [attempt, setAttempt] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    getMetadata(controller.signal)
      .then((value) => {
        setData(value);
        setError("");
      })
      .catch((e) => {
        if (!controller.signal.aborted)
          setError(e instanceof Error ? e.message : "Could not load metadata.");
      });
    return () => controller.abort();
  }, [attempt]);
  const page = data?.pages.find((item) => item.path === path) ?? data?.pages[0];
  return (
    <div className="mt-8">
      {error && (
        <div
          role="alert"
          className="mb-5 rounded-lg bg-red-50 p-4 text-sm text-red-700"
        >
          {error}
          <button
            onClick={() => setAttempt((value) => value + 1)}
            className="ml-4 underline"
          >
            Retry
          </button>
        </div>
      )}
      {!data && !error && <p role="status">Loading SEO metadata…</p>}
      {data && (
        <div className="grid items-start gap-6 xl:grid-cols-2">
          <MetadataForm
            value={data.settings}
            label="SEO defaults"
            onSaved={() => setAttempt((value) => value + 1)}
          />
          <div className="space-y-4">
            <label className="block text-sm font-medium">
              Choose a page
              <select
                value={page?.path ?? ""}
                onChange={(event) => setPath(event.target.value)}
                className="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5"
              >
                {data.pages.map((item) => (
                  <option key={item.path} value={item.path}>
                    {item.label} ({item.path})
                  </option>
                ))}
              </select>
            </label>
            {page && (
              <MetadataForm
                key={page.path}
                path={page.path}
                value={page.metadata}
                label={`${page.label} metadata`}
                onSaved={() => setAttempt((value) => value + 1)}
              />
            )}
            <p className="text-xs leading-5 text-gray-500">
              Edit individual blog, event, and property metadata in their
              content editors. Production domain and credentials remain in
              private server configuration.
            </p>
          </div>
        </div>
      )}
    </div>
  );
}
