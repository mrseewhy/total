import type { LoaderFunctionArgs } from "react-router";
import type { ContentItem, ContentKind, Paging } from "../api/content";
export type PublicImage = {
  url: string;
  alt: string;
  width: number;
  height: number;
};
export type SeoData = {
  title: string;
  description: string;
  canonical: string | null;
  robots: string;
  image: string;
  siteName: string;
  locale: string;
  language: string;
  ogType: string;
  schema: Record<string, unknown> | [];
};
export type PublicPageData = {
  success: boolean;
  status: number;
  type: "static" | "listing" | "detail" | "private";
  path: string;
  heading: string;
  intro: string;
  seo: SeoData;
  redirect?: string;
  kind?: ContentKind;
  html?: string;
  item?: ContentItem;
  image?: PublicImage | null;
  gallery?: PublicImage[];
  items?: (ContentItem & { url: string; image: PublicImage | null })[];
  pagination?: Paging;
};
let initialRead = false;
export async function publicPageLoader({
  request,
}: LoaderFunctionArgs): Promise<PublicPageData> {
  const url = new URL(request.url);
  if (!initialRead) {
    initialRead = true;
    const element = document.getElementById("seo-page-data");
    if (element) {
      try {
        const data = JSON.parse(element.textContent || "") as PublicPageData;
        if (
          data.path === url.pathname &&
          (data.pagination?.page ?? 1) ===
            Number(url.searchParams.get("page") || 1)
        )
          return data;
      } catch {
        /* Fetch fresh data if the bootstrap was incomplete. */
      }
    }
  }
  const response = await fetch(
    "/api/seo?path=" + encodeURIComponent(url.pathname + url.search),
    { signal: request.signal },
  );
  const data = await response.json();
  if (!data.success) throw new Error(data.message || "Could not load page");
  if (data.redirect) {
    const { redirect } = await import("react-router");
    throw redirect(data.redirect, 301);
  }
  return data;
}
