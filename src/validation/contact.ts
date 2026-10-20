import * as z from 'zod'

const byteLimit = (limit: number) => (value: string) => new TextEncoder().encode(value).length <= limit
export const contactSchema = z.object({
  name: z.string().trim().min(1, 'Please enter your full name.').refine(byteLimit(100), 'Name is too long.'),
  email: z.string().trim().pipe(z.email('Please enter a valid email address.')).refine(byteLimit(254), 'Email address is too long.'),
  phone: z.string().trim().refine(byteLimit(30), 'Phone number is too long.'),
  interest: z.enum(['Buying a home', 'Renting a home', 'Selling a property', 'General inquiry'], { error: 'Please select an interest.' }),
  message: z.string().trim().refine(value => new TextEncoder().encode(value).length >= 10, 'Please enter a message of at least 10 characters.').refine(byteLimit(5000), 'Message is too long.'),
  website: z.literal(''),
})
export type ContactField = keyof z.infer<typeof contactSchema>
