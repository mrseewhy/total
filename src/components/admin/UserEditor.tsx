import { FormValidationError, issueFields, useForm } from "../../forms/useForm";
import { Form, FormFeedback, FieldError } from "../forms/Form";
import type { ManagedUser } from "../../api/users";
import { userSchema } from "../../validation/user";
import type { UserFormField } from "../../validation/user";
import AdminModal from "./AdminModal";
import PasswordField from "./PasswordField";
export default function UserEditor({
  user,
  onClose,
  onSave,
}: {
  user: ManagedUser | null;
  onClose: () => void;
  onSave: (data: Record<string, string>) => Promise<void>;
}) {
  const form = useForm();
  const { busy, errors } = form;
  const clear = form.clear;
  const attributes = form.attributes;
  const fieldError = (field: UserFormField) => (
    <FieldError state={form} name={field} />
  );
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(event.currentTarget));
    await form.submit(async () => {
      const validated = userSchema(!user).safeParse(values);
      if (!validated.success)
        throw new FormValidationError(issueFields(validated.error.issues));
      await onSave(validated.data);
      onClose();
    });
  }
  return (
    <AdminModal
      title={user ? "Edit user" : "Create user"}
      onClose={onClose}
      busy={busy}
    >
      <Form state={form} onSubmit={submit} className="space-y-4">
        <fieldset disabled={busy} className="space-y-4">
          <div>
            <label htmlFor="user-name" className="block text-sm">
              Name
            </label>
            <input
              id="user-name"
              name="name"
              required
              maxLength={100}
              defaultValue={user?.name}
              {...attributes("name")}
              className="auth-input"
            />
            {fieldError("name")}
          </div>
          <div>
            <label htmlFor="user-email" className="block text-sm">
              Email
            </label>
            <input
              id="user-email"
              name="email"
              type="email"
              readOnly={!!user}
              required
              maxLength={254}
              defaultValue={user?.email}
              {...attributes("email")}
              className="auth-input"
            />
            {fieldError("email")}
          </div>
          <div>
            <label htmlFor="user-role" className="block text-sm">
              Role
            </label>
            <select
              id="user-role"
              name="role"
              required
              defaultValue={user?.role ?? "Manager"}
              {...attributes("role")}
              className="auth-input"
            >
              {user?.role === "Admin" ? (
                <option>Admin</option>
              ) : (
                <option>Manager</option>
              )}
            </select>
            {fieldError("role")}
          </div>
          <PasswordField
            name="password"
            label={
              user ? "New password (leave blank to keep current)" : "Password"
            }
            required={!user}
            error={errors.password}
            onChange={() => clear("password")}
          />
          <PasswordField
            name="password_confirmation"
            label="Confirm password"
            required={!user}
            error={errors.password_confirmation}
            onChange={() => clear("password_confirmation")}
          />
        </fieldset>
        <FormFeedback state={form} />
        <div className="flex justify-end gap-3 pt-3">
          <button
            type="button"
            disabled={busy}
            onClick={onClose}
            className="rounded-lg border border-gray-200 px-4 py-2 text-sm"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={busy}
            className="rounded-lg bg-gray-800 px-4 py-2 text-sm text-white disabled:opacity-50"
          >
            {busy ? "Saving…" : user ? "Save changes" : "Create user"}
          </button>
        </div>
      </Form>
    </AdminModal>
  );
}
