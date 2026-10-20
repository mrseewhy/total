import * as z from 'zod'
const bytes = (value: string) => new TextEncoder().encode(value).length
export function userSchema(creating: boolean) {
  return z.object({
    name: z.string().trim().min(1, 'Name is required.').refine(value => bytes(value) <= 100, 'Name is too long.'),
    email: z.string().trim().toLowerCase().pipe(z.email('Enter a valid email address.')).refine(value => bytes(value) <= 254, 'Email is too long.'),
    role: z.enum(['Admin', 'Manager'], {error: 'Choose Admin or Manager.'}),
    password: z.string(),
    password_confirmation: z.string(),
  }).superRefine((data, context) => {
    if ((creating || data.password !== '') && (bytes(data.password) < 12 || bytes(data.password) > 72 || data.password.includes('\0'))) {
      context.addIssue({code:'custom',path:['password'],message:'Use a password between 12 and 72 bytes.'})
    }
    if (data.password !== data.password_confirmation) {
      context.addIssue({code:'custom',path:['password_confirmation'],message:'Passwords do not match.'})
    }
  })
}
export type UserFormField = 'name' | 'email' | 'role' | 'password' | 'password_confirmation'
