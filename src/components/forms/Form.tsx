import type { FormHTMLAttributes, ReactNode } from "react";
import type { FormState } from "../../forms/useForm";
export function Form({
  state,
  children,
  ...props
}: FormHTMLAttributes<HTMLFormElement> & {
  state: FormState;
  children: ReactNode;
}) {
  return (
    <form
      {...props}
      ref={(element) => state.register(element)}
      aria-busy={state.busy}
      onChangeCapture={(event) => {
        const target = event.target;
        if (
          target instanceof HTMLInputElement ||
          target instanceof HTMLSelectElement ||
          target instanceof HTMLTextAreaElement
        )
          state.clear(target.name);
        props.onChangeCapture?.(event);
      }}
      onInvalidCapture={(event) => {
        const target = event.target;
        if (
          target instanceof HTMLInputElement ||
          target instanceof HTMLSelectElement ||
          target instanceof HTMLTextAreaElement
        ) {
          state.setErrors((previous) => ({
            ...previous,
            [target.name]: target.validationMessage,
          }));
          state.setMessage("");
        }
        props.onInvalidCapture?.(event);
      }}
    >
      <fieldset
        disabled={state.busy}
        className={`min-w-0 border-0 p-0 disabled:opacity-75 ${(props.className?.match(/\bspace-y-\S+/g) || []).join(" ")}`}
      >
        {children}
      </fieldset>
    </form>
  );
}
export function FieldError({
  state,
  name,
}: {
  state: FormState;
  name: string;
}) {
  return state.errors[name] ? (
    <p
      id={`${state.id}-${name}-error`}
      role="alert"
      className="mt-2 text-xs text-red-600"
    >
      {state.errors[name]}
    </p>
  ) : null;
}
export function FormFeedback({ state }: { state: FormState }) {
  return (
    <>
      {state.error && (
        <div
          data-form-error-summary
          tabIndex={-1}
          role="alert"
          className="my-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
        >
          {state.error}
          {state.retryAfter !== null && state.retryAfter > 0 && (
            <p className="mt-1 text-xs">
              Try again in about {state.retryAfter} seconds.
            </p>
          )}
        </div>
      )}
      {state.message && <SuccessMessage>{state.message}</SuccessMessage>}
    </>
  );
}
export function SuccessMessage({
  title,
  children,
}: {
  title?: string;
  children: ReactNode;
}) {
  return (
    <div
      role="status"
      className="my-4 rounded-xl border border-green-200 bg-green-50 p-6 text-green-900"
    >
      {title && <h3 className="text-xl font-medium">{title}</h3>}
      <div className={title ? "mt-3 text-sm leading-6" : "text-sm leading-6"}>
        {children}
      </div>
    </div>
  );
}
export function SubmitButton({
  busy,
  children,
  pendingLabel = "Saving…",
  className = "auth-button",
}: {
  busy: boolean;
  children: ReactNode;
  pendingLabel?: string;
  className?: string;
}) {
  return (
    <button
      type="submit"
      disabled={busy}
      className={`${className} disabled:cursor-wait disabled:opacity-60`}
    >
      {busy ? pendingLabel : children}
    </button>
  );
}
