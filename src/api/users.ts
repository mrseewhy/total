import { readApiResponse } from "./response";
import { authenticatedFetch, updateCsrf } from "./auth";
export type ManagedUser = {
  id: number;
  name: string;
  email: string;
  role: "Admin" | "Manager";
  is_active: boolean;
  created_at: string;
};
export type UsersPage = {
  data: ManagedUser[];
  pagination: { page: number; pages: number; total: number; perPage: number };
};
export async function usersRequest(
  path = "",
  method = "GET",
  data?: Record<string, unknown>,
  signal?: AbortSignal,
) {
  const response = await authenticatedFetch(`/api/users${path}`, {
    method,
    signal,
    headers: { "Content-Type": "application/json" },
    ...(data ? { body: JSON.stringify(data) } : {}),
  });
  const result = await readApiResponse(response);
  if (result.csrf) updateCsrf(result.csrf);
  return result;
}
