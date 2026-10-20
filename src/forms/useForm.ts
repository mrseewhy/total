import { useCallback, useEffect, useId, useRef, useState } from "react";
import { ApiError, formFailure } from "../api/response";
import type { FieldErrors } from "../api/response";
export class FormValidationError extends Error {
  fields: FieldErrors;
  constructor(fields: FieldErrors) {
    super("Please check the highlighted fields.");
    this.fields = fields;
  }
}
export function issueFields(
  issues: readonly { path: readonly PropertyKey[]; message: string }[],
): FieldErrors {
  const errors: FieldErrors = {};
  for (const issue of issues) {
    const key = issue.path.join(".");
    if (key && !["__proto__", "constructor", "prototype"].includes(key))
      errors[key] ??= issue.message;
  }
  return errors;
}
export function useForm() {
  const id = useId();
  const formRef = useRef<HTMLFormElement | null>(null);
  const register = useCallback((element: HTMLFormElement | null) => {
    formRef.current = element;
  }, []);
  const locked = useRef(false);
  const mounted = useRef(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [errors, setErrors] = useState<FieldErrors>({});
  const [retryAfter, setRetryAfter] = useState<number | null>(null);
  useEffect(() => {
    mounted.current = true;
    return () => {
      mounted.current = false;
    };
  }, []);
  function clear(name: string) {
    setErrors((previous) => ({ ...previous, [name]: "" }));
    setMessage("");
  }
  function focus(fields: FieldErrors) {
    const first = Object.keys(fields)[0];
    const input = formRef.current?.elements.namedItem(first);
    if (input instanceof HTMLElement) input.focus();
    else
      formRef.current
        ?.querySelector<HTMLElement>("[data-form-error-summary]")
        ?.focus();
  }
  async function submit<T>(
    action: () => Promise<T>,
    success?: string | ((result: T) => string),
  ): Promise<T | undefined> {
    if (locked.current) return;
    locked.current = true;
    setBusy(true);
    setError("");
    setMessage("");
    setErrors({});
    setRetryAfter(null);
    try {
      const result = await action();
      if (mounted.current && success)
        setMessage(typeof success === "function" ? success(result) : success);
      return result;
    } catch (cause) {
      if (!mounted.current) return;
      if (cause instanceof FormValidationError) {
        setErrors(cause.fields);
        setError(cause.message);
        queueMicrotask(() => focus(cause.fields));
      } else {
        const failure = formFailure(cause);
        setError(failure.message);
        setErrors(failure.fields);
        setRetryAfter(failure.retryAfter);
        if (Object.keys(failure.fields).length)
          queueMicrotask(() => focus(failure.fields));
      }
    } finally {
      locked.current = false;
      if (mounted.current) setBusy(false);
    }
  }
  const attributes = (name: string) => ({
    "aria-invalid": !!errors[name],
    "aria-describedby": errors[name] ? `${id}-${name}-error` : undefined,
  });
  return {
    id,
    register,
    busy,
    error,
    message,
    errors,
    retryAfter,
    submit,
    clear,
    attributes,
    setErrors,
    setError,
    setMessage,
  };
}
export type FormState = ReturnType<typeof useForm>;
export { ApiError };
