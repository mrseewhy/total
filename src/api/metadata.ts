import { cmsRequest } from "./content";
export type PageMetadata = {
  title: string;
  description: string;
  image: string;
  noindex: boolean;
};
export type SeoSettings = {
  site_name: string;
  description: string;
  default_image: string;
  language: string;
  locale: string;
  noindex: boolean;
};
export type MetadataPage = {
  path: string;
  label: string;
  metadata: PageMetadata;
};
export type MetadataResponse = { settings: SeoSettings; pages: MetadataPage[] };
export async function getMetadata(
  signal?: AbortSignal,
): Promise<MetadataResponse> {
  return cmsRequest("metadata", "GET", undefined, signal);
}
export async function saveMetadata(data: Record<string, unknown>) {
  return cmsRequest("metadata", "PUT", data);
}
