export type FieldErrors = Record<string, string>;
export class ApiError extends Error {
  status: number;
  fields: FieldErrors;
  retryAfter: number | null;
  constructor(
    message: string,
    status = 0,
    fields: FieldErrors = {},
    retryAfter: number | null = null,
  ) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.fields = fields;
    this.retryAfter = retryAfter;
  }
}
export function normalizeFieldErrors(value: unknown): FieldErrors {
  const fields: FieldErrors = {};
  if (!value || typeof value !== "object" || Array.isArray(value))
    return fields;
  for (const [key, input] of Object.entries(value)) {
    const message = Array.isArray(input)
      ? input.find((v) => typeof v === "string")
      : input;
    if (
      typeof message === "string" &&
      message &&
      !["__proto__", "constructor", "prototype"].includes(key)
    )
      fields[key] = message;
  }
  return fields;
}
export async function readApiResponse(response: Response) {
  let result;
  try {
    result = await response.json();
  } catch {
    throw new ApiError(
      response.status >= 500
        ? "The server could not complete this request. Please try again."
        : "The server returned an unreadable response. Please try again.",
      response.status,
    );
  }
  if (!result || typeof result !== "object" || Array.isArray(result))
    throw new ApiError(
      "The server returned an unreadable response. Please try again.",
      response.status,
    );
  if (!response.ok || result.success !== true) {
    const defaults: Record<number, string> = {
      401: "Your session has expired. Please sign in again.",
      403: "You do not have permission to do that.",
      413: "This submission is too large.",
      419: "Your session has changed. Reload the page and try again.",
      422: "Please check the highlighted fields.",
      429: "Too many attempts. Please try again later.",
    };
    const raw = response.headers.get("Retry-After");
    let retry: number | null = null;
    if (raw) {
      const parsed = /^\d+$/.test(raw)
        ? Number(raw)
        : Math.ceil((Date.parse(raw) - Date.now()) / 1000);
      if (Number.isFinite(parsed)) retry = Math.max(0, parsed);
    }
    const message =
      response.status >= 500 &&
      !(
        response.status === 503 &&
        (result.maintenance === true || result.code === "mail_unavailable")
      )
        ? "The server could not complete this request. Please try again."
        : typeof result.message === "string" && result.message
          ? result.message
          : defaults[response.status] ||
            "Could not complete this request. Please try again.";
    throw new ApiError(
      message,
      response.status,
      normalizeFieldErrors(result.errors),
      retry,
    );
  }
  return result;
}
export function formFailure(error: unknown): ApiError {
  if (error instanceof ApiError) return error;
  if (
    error instanceof DOMException &&
    ["TimeoutError", "AbortError"].includes(error.name)
  )
    return new ApiError(
      "The request did not finish. Check whether it was saved before trying again.",
    );
  if (error instanceof TypeError)
    return new ApiError(
      "Could not reach the server. Check your connection. Before retrying, check whether your submission was saved.",
    );
  return new ApiError(
    error instanceof Error
      ? error.message
      : "Could not complete this request. Please try again.",
  );
}
