import * as z from "zod";

export const subscriberSchema = z.object({
  email: z.string().trim().pipe(z.email("Invalid email address")),
});
