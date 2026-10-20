import { useState } from "react";
import { submitContact } from "../../api/contacts";
import { contactSchema } from "../../validation/contact";
import type { ContactField } from "../../validation/contact";
import { FormValidationError, issueFields, useForm } from "../../forms/useForm";
import { FieldError, Form, FormFeedback, SuccessMessage } from "../forms/Form";

export default function ContactForm() {
  const form = useForm();
  const { busy: loading } = form;
  const [sent, setSent] = useState(false);
  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const fields = new FormData(event.currentTarget);
    await form.submit(async () => {
      const data = Object.fromEntries(
        ["name", "email", "phone", "interest", "message"].map((name) => [
          name,
          String(fields.get(name) ?? ""),
        ]),
      );
      const website = String(fields.get("contact_reference_check") ?? "");
      if (website)
        throw new Error(
          "Your submission could not be processed. Please reload the page and enter your details again.",
        );
      const parsed = contactSchema.safeParse({ ...data, website });
      if (!parsed.success)
        throw new FormValidationError(issueFields(parsed.error.issues));
      await submitContact(parsed.data);
      setSent(true);
    });
  }
  if (sent)
    return (
      <SuccessMessage title="Thank you for getting in touch!">
        Your inquiry has been received. Our team will be in touch.
      </SuccessMessage>
    );
  const errorFor = (name: ContactField) => (
    <FieldError state={form} name={name} />
  );
  const accessibility = form.attributes;
  const field =
    "mt-2 w-full rounded-lg border border-stone-300 bg-white px-4 py-3 text-sm outline-none focus:border-[#365d4b]";
  return (
    <Form state={form} onSubmit={handleSubmit} className="space-y-5">
      <div
        aria-hidden="true"
        className="absolute -left-[10000px] size-px overflow-hidden"
      >
        <label htmlFor="contact-reference-check">Leave this field empty</label>
        <input
          key="contact-reference-check"
          defaultValue=""
          id="contact-reference-check"
          name="contact_reference_check"
          type="text"
          tabIndex={-1}
          autoComplete="off"
          data-lpignore="true"
          data-1p-ignore
          data-bwignore="true"
        />
      </div>
      <fieldset disabled={loading} className="space-y-5">
        <div className="grid gap-5 sm:grid-cols-2">
          <label className="text-xs font-medium">
            Full name
            <input
              name="name"
              {...accessibility("name")}
              autoComplete="name"
              required
              maxLength={100}
              placeholder="Your full name"
              className={field}
            />
            {errorFor("name")}
          </label>
          <label className="text-xs font-medium">
            Email address
            <input
              name="email"
              {...accessibility("email")}
              type="email"
              autoComplete="email"
              required
              maxLength={254}
              placeholder="you@example.com"
              className={field}
            />
            {errorFor("email")}
          </label>
        </div>
        <div className="grid gap-5 sm:grid-cols-2">
          <label className="text-xs font-medium">
            Phone <span className="text-stone-400">(optional)</span>
            <input
              name="phone"
              {...accessibility("phone")}
              type="tel"
              autoComplete="tel"
              maxLength={30}
              placeholder="Your phone number"
              className={field}
            />
            {errorFor("phone")}
          </label>
          <label className="text-xs font-medium">
            I'm interested in
            <select
              required
              name="interest"
              {...accessibility("interest")}
              className={field}
            >
              <option>Buying a home</option>
              <option>Renting a home</option>
              <option>Selling a property</option>
              <option>General inquiry</option>
            </select>
            {errorFor("interest")}
          </label>
        </div>
        <label className="block text-xs font-medium">
          Your message
          <textarea
            name="message"
            {...accessibility("message")}
            required
            minLength={10}
            maxLength={5000}
            rows={4}
            placeholder="Tell us a little about what you're looking for…"
            className={`${field} resize-y`}
          />
          {errorFor("message")}
        </label>
        <button
          type="submit"
          disabled={loading}
          className="flex w-full items-center justify-between rounded-lg bg-[#294e3e] px-5 py-3.5 text-sm font-medium text-white transition hover:bg-[#1e3b2e]"
        >
          {loading ? "Sending…" : "Send inquiry"}{" "}
          <span aria-hidden="true">↗</span>
        </button>
      </fieldset>
      <FormFeedback state={form} />
    </Form>
  );
}
