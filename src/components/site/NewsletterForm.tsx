import { useState } from "react";
import { subscriberSchema } from "../../validation/validation";
import { readApiResponse } from "../../api/response";
import { FormValidationError, issueFields, useForm } from "../../forms/useForm";
import {
  FieldError,
  Form,
  FormFeedback,
  SubmitButton,
  SuccessMessage,
} from "../forms/Form";
export default function NewsletterForm() {
  const form = useForm();
  const [subscribed, setSubscribed] = useState(false);
  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    await form.submit(async () => {
      if (data.get("website"))
        throw new Error(
          "Your submission could not be processed. Please reload the page and enter your details again.",
        );
      const parsed = subscriberSchema.safeParse({
        email: String(data.get("email") ?? ""),
      });
      if (!parsed.success)
        throw new FormValidationError(issueFields(parsed.error.issues));
      const result = await readApiResponse(
        await fetch("/api/subscribers", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ ...parsed.data, website: "" }),
          signal: AbortSignal.timeout(15000),
        }),
      );
      setSubscribed(true);
      return result;
    });
  }
  if (subscribed)
    return (
      <SuccessMessage title="Thank you for subscribing!">
        You're on the list. Look out for the latest news and updates from Haven.
      </SuccessMessage>
    );
  return (
    <Form state={form} onSubmit={handleSubmit} className="w-full">
      <div
        aria-hidden="true"
        className="absolute -left-[10000px] size-px overflow-hidden"
      >
        <label htmlFor={`${form.id}-website`}>Leave this field empty</label>
        <input
          id={`${form.id}-website`}
          name="website"
          type="text"
          tabIndex={-1}
          autoComplete="off"
        />
      </div>
      <label
        htmlFor={`${form.id}-email`}
        className="mb-2 block text-xs font-medium"
      >
        Email address
      </label>
      <div className="flex flex-col gap-3 sm:flex-row">
        <div className="w-full">
          <input
            id={`${form.id}-email`}
            {...form.attributes("email")}
            name="email"
            type="email"
            autoComplete="email"
            required
            minLength={3}
            maxLength={254}
            placeholder="you@example.com"
            className="h-14 w-full min-w-0 rounded-lg border border-stone-300 bg-white px-4 py-3.5 text-sm outline-none focus:border-[#365d4b]"
          />
          <FieldError state={form} name="email" />
        </div>
        <SubmitButton
          busy={form.busy}
          pendingLabel="Subscribing…"
          className="rounded-lg bg-[#294e3e] px-5 py-3.5 text-sm font-medium text-white"
        >
          Subscribe ↗
        </SubmitButton>
      </div>
      <FormFeedback state={form} />
    </Form>
  );
}
