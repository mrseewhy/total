import { readApiResponse } from "./response";
import { authenticatedFetch } from "./auth";
import type { NewsletterSubscriber, Pagination } from "../types/subscriber";
export async function fetchSubscribers(
  page: number,
  signal: AbortSignal,
): Promise<{ data: NewsletterSubscriber[]; pagination: Pagination }> {
  const response = await authenticatedFetch(`/api/subscribers?page=${page}`, {
    signal,
  });
  const result = await readApiResponse(response);
  return result;
}
