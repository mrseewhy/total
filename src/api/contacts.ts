import { readApiResponse } from "./response";
import { authenticatedFetch } from "./auth";
export type ContactInquiry = {
  id: number;
  name: string;
  email: string;
  phone: string;
  interest: string;
  message: string;
  is_read: boolean;
  created_at: string;
};
export type ContactPage = {
  data: ContactInquiry[];
  pagination: { page: number; pages: number; total: number; perPage: number };
};
async function request(path: string, options?: RequestInit) {
  const response = await (
    options?.method === "POST" ? fetch : authenticatedFetch
  )(`/api/contacts${path}`, options);
  const result = await readApiResponse(response);
  return result;
}
export const fetchContacts = (
  page: number,
  signal: AbortSignal,
): Promise<ContactPage> => request(`?page=${page}`, { signal });
export async function setContactRead(
  id: number,
  is_read: boolean,
): Promise<ContactInquiry> {
  const result = await request(`/${id}/read`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ is_read }),
  });
  return result.data;
}
export const submitContact = (data: Record<string, string>) =>
  request("", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
    signal: AbortSignal.timeout(15000),
  });
