import * as z from "zod";
const text = (limit: number) =>
  z
    .string()
    .trim()
    .refine(
      (value) => new TextEncoder().encode(value).length <= limit,
      `Use no more than ${limit} bytes.`,
    );
export const mediaDetailsSchema = z.object({
  title: text(200).refine((value) => !!value, "Title is required."),
  alt_text: text(500),
  caption: text(1000),
});
