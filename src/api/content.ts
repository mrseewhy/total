import { readApiResponse } from "./response";
import type { PartialBlock } from "@blocknote/core";
import { authenticatedFetch } from "./auth";
import type { ContentKind } from "../content/modules";
export type { ContentKind } from "../content/modules";
export type Paging = {
  page: number;
  pages: number;
  total: number;
  perPage: number;
};
export type MediaItem = {
  id: number;
  url: string;
  filename: string;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  width: number;
  height: number;
  title: string;
  alt_text: string;
  caption: string;
  created_at: string;
  used_by: { content_kind: ContentKind; content_id: number }[];
};
export type ContentDetails = {
  location?: string;
  starts_at?: string | null;
  ends_at?: string | null;
  price?: string;
  currency?: string;
  listing_type?: "sale" | "rent";
  availability?: "available" | "under_offer" | "sold" | "rented";
  bedrooms?: number;
  bathrooms?: number;
  gallery_ids?: number[];
};
export type ContentItem = {
  id: number;
  title: string;
  slug: string;
  excerpt: string;
  content: PartialBlock[];
  details: ContentDetails;
  status: "draft" | "scheduled" | "published";
  effective_status: "draft" | "scheduled" | "published";
  publish_at: string | null;
  featured_media_id: number | null;
  seo_title: string;
  seo_description: string;
  seo_noindex: boolean;
  author_id: number;
  created_at: string;
  updated_at: string;
};
export type ContentInput = Pick<
  ContentItem,
  | "title"
  | "slug"
  | "excerpt"
  | "content"
  | "details"
  | "status"
  | "publish_at"
  | "featured_media_id"
  | "seo_title"
  | "seo_description"
  | "seo_noindex"
>;
export async function cmsRequest(
  path: string,
  method = "GET",
  data?: unknown,
  signal?: AbortSignal,
) {
  const response = await authenticatedFetch(`/api/${path}`, {
    method,
    signal,
    ...(data instanceof FormData
      ? { body: data }
      : data !== undefined
        ? {
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data),
          }
        : {}),
  });
  return readApiResponse(response);
}
export async function getContent(
  kind: ContentKind,
  id: number,
  signal?: AbortSignal,
): Promise<ContentItem> {
  return (await cmsRequest(`${kind}/${id}`, "GET", undefined, signal)).data;
}
export async function listContent(
  kind: ContentKind,
  page: number,
  search: string,
  status: string,
  signal?: AbortSignal,
): Promise<{ data: ContentItem[]; pagination: Paging }> {
  return cmsRequest(
    `${kind}?${new URLSearchParams({ page: String(page), search, status })}`,
    "GET",
    undefined,
    signal,
  );
}
export async function saveContent(
  kind: ContentKind,
  data: ContentInput,
  id?: number,
): Promise<ContentItem> {
  return (
    await cmsRequest(id ? `${kind}/${id}` : kind, id ? "PATCH" : "POST", data)
  ).data;
}
export async function listMedia(
  page: number,
  search: string,
  signal?: AbortSignal,
  unused = false,
): Promise<{ data: MediaItem[]; pagination: Paging }> {
  return cmsRequest(
    `media?${new URLSearchParams({ page: String(page), search, unused: unused ? "1" : "0" })}`,
    "GET",
    undefined,
    signal,
  );
}
export async function getMedia(
  id: number,
  signal?: AbortSignal,
): Promise<MediaItem> {
  return (await cmsRequest(`media/${id}`, "GET", undefined, signal)).data;
}
export async function uploadMedia(file: File): Promise<MediaItem> {
  if (
    !["image/jpeg", "image/png", "image/webp", "image/gif"].includes(file.type)
  )
    throw new Error("Choose a JPEG, PNG, WebP or GIF image.");
  if (file.size > 8 * 1024 * 1024)
    throw new Error("Images must be 8 MB or smaller.");
  const body = new FormData();
  body.append("file", file);
  return (await cmsRequest("media", "POST", body)).data;
}
export const utcDate = (date: string) => date.replace(" ", "T") + "Z";
export const formatDate = (date: string | null) =>
  date ? new Date(utcDate(date)).toLocaleString() : "—";
