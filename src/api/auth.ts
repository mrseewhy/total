import { readApiResponse } from "./response";
export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: string;
  two_factor_enabled: boolean;
};
let csrf = "";
export function updateCsrf(token: string) {
  csrf = token;
}
let sessionRequest: Promise<{
  user: AuthUser | null;
  csrf: string;
  two_factor_pending?: boolean;
}> | null = null;
export async function getSession() {
  sessionRequest ??= fetch("/api/auth/me", {
    credentials: "same-origin",
    cache: "no-store",
  })
    .then(async (response) => {
      const result = await readApiResponse(response);
      csrf = result.csrf;
      return result;
    })
    .finally(() => {
      sessionRequest = null;
    });
  return sessionRequest;
}
export async function authenticatedFetch(
  url: string,
  options: RequestInit = {},
) {
  const method = (options.method || "GET").toUpperCase();
  const headers = new Headers(options.headers);
  if (!["GET", "HEAD"].includes(method)) {
    if (!csrf) await getSession();
    headers.set("X-CSRF-Token", csrf);
  }
  const response = await fetch(url, {
    ...options,
    headers,
    credentials: "same-origin",
  });
  if (response.status === 401 && !url.endsWith("/auth/login"))
    window.dispatchEvent(new Event("auth:expired"));
  return response;
}
export async function authAction(
  action: string,
  data: Record<string, string | boolean> = {},
  method = "POST",
) {
  const response = await authenticatedFetch(`/api/auth/${action}`, {
    method,
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  });
  const result = await readApiResponse(response);
  if (result.csrf) csrf = result.csrf;
  if (action === "logout" || action === "reset-password") csrf = "";
  return result;
}
