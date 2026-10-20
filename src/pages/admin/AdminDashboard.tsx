import ActivityFeed from "../../components/admin/ActivityFeed";
import { useDashboard } from "../../components/admin/dashboardContext";
import { Link } from "react-router";
export default function AdminDashboard() {
  const { data, error, refresh } = useDashboard();
  const cards = data
    ? [
        { label: "Pages", total: data.counts.pages, to: "pages" },
        {
          label: "Unread contact inquiries",
          total: data.counts.unread,
          to: "contacts",
        },
        ...(data.counts.users === undefined
          ? []
          : [{ label: "Users", total: data.counts.users, to: "users" }]),
        ...data.modules.map((m) => ({
          label: m.label,
          total: m.total,
          to: m.id,
        })),
      ]
    : [];
  return (
    <>
      {error && (
        <p role="alert" className="mt-6 text-red-700">
          {error}{" "}
          <button onClick={refresh} className="underline">
            Retry
          </button>
        </p>
      )}
      {!data && !error && (
        <p role="status" className="mt-6">
          Loading overview…
        </p>
      )}
      <section
        aria-label="Website overview"
        className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
      >
        {cards.map((card) => (
          <Link
            key={card.to}
            to={`/site-admin/${card.to}`}
            className="rounded-xl border border-gray-200 bg-white p-6 transition hover:border-green-700"
          >
            <p className="text-sm text-gray-500">{card.label}</p>
            <p className="mt-3 text-3xl font-semibold">
              {card.total.toLocaleString()}
            </p>
            <p className="mt-3 text-xs text-green-800">
              View {card.label.toLowerCase()} →
            </p>
          </Link>
        ))}
      </section>
      <ActivityFeed compact />
    </>
  );
}
