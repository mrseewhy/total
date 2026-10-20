import * as z from "zod";
const email = z
  .string()
  .trim()
  .toLowerCase()
  .max(254, "Email is too long.")
  .pipe(z.email("Enter a valid email address."));
const password = z
  .string()
  .refine(
    (v) =>
      new TextEncoder().encode(v).length >= 12 &&
      new TextEncoder().encode(v).length <= 72 &&
      !v.includes("\0"),
    "Use a password between 12 and 72 bytes.",
  );
export const loginSchema = z.object({
  email,
  password: z
    .string()
    .min(1, "Enter your password.")
    .refine(
      (v) => new TextEncoder().encode(v).length <= 72 && !v.includes("\0"),
      "Invalid email or password.",
    ),
});
export const forgotSchema = z.object({ email });
export const resetSchema = z
  .object({ password, password_confirmation: z.string() })
  .refine((v) => v.password === v.password_confirmation, {
    path: ["password_confirmation"],
    message: "Passwords do not match.",
  });
export const profileSchema = z.object({
  name: z
    .string()
    .trim()
    .min(1, "Name is required.")
    .refine(
      (v) => new TextEncoder().encode(v).length <= 100,
      "Name must be at most 100 bytes.",
    ),
});
export const passwordSchema = z
  .object({
    current_password: z.string().min(1, "Enter your current password."),
    password,
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, {
    path: ["password_confirmation"],
    message: "Passwords do not match.",
  });
