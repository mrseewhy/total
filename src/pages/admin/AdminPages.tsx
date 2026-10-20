import { Eye } from "lucide-react";
import { useDashboard } from "../../components/admin/dashboardContext";
export default function AdminPages() {
  const { data, error, refresh } = useDashboard();
  return (
    <section className="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white">
      <div className="border-b border-gray-100 p-6">
        <h2 className="font-semibold">Website pages</h2>
        <p className="mt-2 text-sm text-gray-500">
          Main pages and content listings. Individual content detail pages are
          excluded.
        </p>
      </div>
      {error && (
        <p role="alert" className="p-6">
          {error}{" "}
          <button onClick={refresh} className="underline">
            Retry
          </button>
        </p>
      )}
      {!data && !error ? (
        <p role="status" className="p-6">
          Loading pages…
        </p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-gray-50 text-gray-500">
              <tr>
                <th className="px-6 py-3">Page</th>
                <th className="px-6 py-3">URL</th>
                <th className="px-6 py-3">View</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {data?.pages.map((page) => (
                <tr key={page.path}>
                  <td className="px-6 py-4 font-medium">{page.label}</td>
                  <td className="px-6 py-4 text-gray-500">{page.path}</td>
                  <td className="px-6 py-4">
                    <a
                      href={page.path}
                      target="_blank"
                      rel="noopener noreferrer"
                      aria-label={`View ${page.label} (opens in new tab)`}
                      className="inline-flex rounded-lg border border-gray-200 p-2 text-green-800"
                    >
                      <Eye className="size-4" />
                    </a>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
