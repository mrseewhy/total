import Logo from "../../components/Logo";
import { contentModule } from "../../content/modules";
import { BriefcaseBusiness } from "lucide-react";
import { Link, useLoaderData } from "react-router";
import type { PublicPageData } from "../../seo/pageData";
import { formatDate } from "../../api/content";
export default function PublicContentPage() {
  const page = useLoaderData() as PublicPageData;
  const profile = page.kind ? contentModule(page.kind).profile : "content";
  if (page.status !== 200)
    return (
      <main className="mx-auto max-w-7xl px-6 py-24 lg:px-10">
        <h1 className="font-serif text-5xl">Page not found.</h1>
        <p className="mt-5 text-sm text-stone-500">
          This page does not exist or is not published.
        </p>
        <Link
          to="/"
          className="mt-8 inline-block rounded-lg bg-[#294e3e] px-5 py-3 text-sm text-white"
        >
          Back to home
        </Link>
      </main>
    );
  if (page.type === "listing")
    return (
      <main className="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <p className="text-xs tracking-widest text-[#8b785b]">EXPLORE HAVEN</p>
        <h1 className="mt-4 font-serif text-5xl">{page.heading}</h1>
        <p className="mt-5 max-w-2xl text-sm leading-7 text-stone-500">
          {page.intro}
        </p>
        <div className="mt-12 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
          {page.items?.map((item) => (
            <article
              key={item.id}
              className="overflow-hidden rounded-xl border border-stone-200 bg-white"
            >
              <Link to={item.url}>
                {item.image ? (
                  <img
                    src={item.image.url}
                    alt={item.image.alt}
                    width={item.image.width}
                    height={item.image.height}
                    loading="lazy"
                    className="aspect-[4/3] w-full object-cover"
                  />
                ) : profile === "service" ? (
                  <div className="px-6 pt-6">
                    <span className="flex size-12 items-center justify-center rounded-xl bg-[#eeeae1] text-[#294e3e]">
                      <BriefcaseBusiness
                        className="size-6"
                        aria-hidden="true"
                      />
                    </span>
                  </div>
                ) : (
                  <div className="flex aspect-[4/3] items-center justify-center bg-[#eeeae1] font-serif text-3xl text-[#6d806e]">
                    <Logo />
                  </div>
                )}
                <div className="p-6">
                  <h2 className="font-serif text-2xl">{item.title}</h2>
                  {item.excerpt && (
                    <p className="mt-3 text-sm leading-6 text-stone-500">
                      {item.excerpt}
                    </p>
                  )}
                  {profile === "property" && (
                    <p className="mt-4 text-sm font-medium">
                      {item.details.currency}{" "}
                      {Number(item.details.price).toLocaleString()} · For{" "}
                      {item.details.listing_type}
                    </p>
                  )}
                  {profile === "event" && (
                    <p className="mt-4 text-xs text-stone-500">
                      {formatDate(item.details.starts_at ?? null)} ·{" "}
                      {item.details.location}
                    </p>
                  )}
                  <span className="mt-5 inline-block text-xs font-medium text-[#294e3e]">
                    {profile === "service" ? "Explore service" : "View details"}{" "}
                    ↗
                  </span>
                </div>
              </Link>
            </article>
          ))}
        </div>
        {!page.items?.length && (
          <p className="mt-12 rounded-xl border border-stone-200 p-10 text-center text-sm text-stone-500">
            No published items yet. Check back soon.
          </p>
        )}
        {page.pagination && (
          <nav
            aria-label="Pagination"
            className="mt-12 flex items-center justify-between text-sm text-stone-500"
          >
            <span>
              Page {page.pagination.page} of {page.pagination.pages}
            </span>
            <div className="flex gap-6">
              {page.pagination.page > 1 && (
                <Link
                  to={
                    page.path +
                    (page.pagination.page === 2
                      ? ""
                      : "?page=" + (page.pagination.page - 1))
                  }
                >
                  Previous
                </Link>
              )}
              {page.pagination.page < page.pagination.pages && (
                <Link to={page.path + "?page=" + (page.pagination.page + 1)}>
                  Next
                </Link>
              )}
            </div>
          </nav>
        )}
      </main>
    );
  const item = page.item!;
  const base = page.path.split("/")[1];
  return (
    <main className="mx-auto max-w-5xl px-6 py-12 lg:px-10">
      <nav
        aria-label="Breadcrumb"
        className="mb-8 flex flex-wrap gap-3 text-xs text-stone-500"
      >
        <Link to="/">Home</Link>
        <span>/</span>
        <Link to={"/" + base} className="capitalize">
          {base}
        </Link>
        <span>/</span>
        <span>{item.title}</span>
      </nav>
      <article>
        <header>
          <h1 className="font-serif text-4xl leading-tight sm:text-6xl">
            {item.title}
          </h1>
          {profile === "article" && (
            <p className="mt-5 text-xs text-stone-500">
              <time dateTime={item.publish_at?.replace(" ", "T") + "Z"}>
                {formatDate(item.publish_at)}
              </time>{" "}
              · Haven Real Estate
            </p>
          )}
          {item.excerpt && (
            <p className="mt-6 text-lg leading-8 text-stone-500">
              {item.excerpt}
            </p>
          )}
        </header>
        {page.image && (
          <img
            src={page.image.url}
            alt={page.image.alt}
            width={page.image.width}
            height={page.image.height}
            fetchPriority="high"
            className="mt-10 max-h-[560px] w-full rounded-2xl object-cover"
          />
        )}
        {profile === "event" && (
          <div className="mt-8 rounded-xl bg-[#eeeae1] p-6 text-sm leading-7">
            <p>{item.details.location}</p>
            <p>
              {formatDate(item.details.starts_at ?? null)}
              {item.details.ends_at
                ? " – " + formatDate(item.details.ends_at)
                : ""}
            </p>
          </div>
        )}
        {profile === "property" && (
          <div className="mt-8 flex flex-wrap gap-6 rounded-xl bg-[#eeeae1] p-6 text-sm">
            <strong>
              {item.details.currency}{" "}
              {Number(item.details.price).toLocaleString()}
            </strong>
            <span>For {item.details.listing_type}</span>
            <span>{item.details.location}</span>
            <span>
              {item.details.bedrooms} bedrooms · {item.details.bathrooms}{" "}
              bathrooms
            </span>
            <span className="capitalize">
              {item.details.availability?.replace("_", " ")}
            </span>
          </div>
        )}
        <div
          className="site-prose mt-10"
          dangerouslySetInnerHTML={{ __html: page.html || "" }}
        />
        {!!page.gallery?.length && (
          <div className="mt-10 grid gap-5 sm:grid-cols-2">
            {page.gallery.map((image) => (
              <img
                key={image.url}
                src={image.url}
                alt={image.alt}
                width={image.width}
                height={image.height}
                loading="lazy"
                className="aspect-[4/3] w-full rounded-xl object-cover"
              />
            ))}
          </div>
        )}
        <Link
          to={
            profile === "property"
              ? "/contact?property=" + encodeURIComponent(item.title)
              : profile === "service"
                ? "/contact?service=" + encodeURIComponent(item.title)
                : "/contact"
          }
          className="mt-12 inline-block rounded-lg bg-[#294e3e] px-5 py-3 text-sm text-white"
        >
          {profile === "property"
            ? "Inquire about this property"
            : profile === "service"
              ? "Discuss this service"
              : "Contact our team"}{" "}
          ↗
        </Link>
      </article>
    </main>
  );
}
