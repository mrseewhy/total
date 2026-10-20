import { useEffect } from "react";
import { Outlet, useLocation, useMatches } from "react-router";
import type { PublicPageData, SeoData } from "./pageData";
function applySeo(seo: SeoData) {
  document.title = seo.title;
  document.documentElement.lang = seo.language;
  for (const selector of [
    'meta[name="description"]',
    'meta[name="robots"]',
    'meta[name^="twitter:"]',
    'meta[property^="og:"]',
    'link[rel="canonical"]',
    'script[data-seo="structured"]',
  ])
    document.head.querySelectorAll(selector).forEach((node) => node.remove());
  const addMeta = (attribute: string, name: string, value: string) => {
    const meta = document.createElement("meta");
    meta.setAttribute(attribute, name);
    meta.content = value;
    document.head.appendChild(meta);
  };
  for (const [name, value] of Object.entries({
    description: seo.description,
    robots: seo.robots,
    "twitter:card": "summary_large_image",
    "twitter:title": seo.title,
    "twitter:description": seo.description,
    "twitter:image": seo.image,
  }))
    addMeta("name", name, value);
  for (const [name, value] of Object.entries({
    "og:title": seo.title,
    "og:description": seo.description,
    "og:type": seo.ogType,
    "og:site_name": seo.siteName,
    "og:locale": seo.locale,
    "og:image": seo.image,
    ...(seo.canonical ? { "og:url": seo.canonical } : {}),
  }))
    addMeta("property", name, value);
  if (seo.canonical) {
    const link = document.createElement("link");
    link.rel = "canonical";
    link.href = seo.canonical;
    document.head.appendChild(link);
  }
  if (Object.keys(seo.schema).length) {
    const script = document.createElement("script");
    script.type = "application/ld+json";
    script.dataset.seo = "structured";
    script.textContent = JSON.stringify(seo.schema);
    document.head.appendChild(script);
  }
}
export default function SeoBoundary() {
  const location = useLocation();
  const matches = useMatches();
  const data = [...matches]
    .reverse()
    .map((match) => match.loaderData as PublicPageData | undefined)
    .find((value) => value?.seo);
  useEffect(() => {
    if (/^\/(site-admin|admin|dashboard)(\/|$)/.test(location.pathname)) {
      applySeo({
        title: "Site administration | Haven Real Estate",
        description: "Private site administration.",
        canonical: null,
        robots: "noindex, nofollow",
        image: "",
        siteName: "Haven Real Estate",
        locale: "en_NG",
        language: "en",
        ogType: "website",
        schema: [],
      });
    } else if (data) applySeo(data.seo);
  }, [location.pathname, data]);
  return <Outlet />;
}
export function SeoError() {
  useEffect(() => {
    applySeo({
      title: "Temporarily unavailable | Haven Real Estate",
      description: "Please try again shortly.",
      canonical: null,
      robots: "noindex, follow",
      image: "",
      siteName: "Haven Real Estate",
      locale: "en_NG",
      language: "en",
      ogType: "website",
      schema: [],
    });
  }, []);
  return (
    <main className="mx-auto max-w-xl px-6 py-20">
      <h1 className="text-3xl font-semibold">Temporarily unavailable</h1>
      <p className="mt-5 text-sm text-gray-500">Please try again shortly.</p>
      <button
        onClick={() => window.location.reload()}
        className="mt-6 underline"
      >
        Try again
      </button>
    </main>
  );
}
