import { useId, useState } from "react";
import { Eye, EyeOff } from "lucide-react";
export default function PasswordField({
  name,
  label,
  required,
  error,
  onChange,
  autoComplete = "new-password",
}: {
  name: string;
  label: string;
  required: boolean;
  error?: string;
  onChange: () => void;
  autoComplete?: string;
}) {
  const [visible, setVisible] = useState(false);
  const id = useId();
  return (
    <div>
      <label htmlFor={id} className="block text-sm">
        {label}
      </label>
      <div className="relative">
        <input
          id={id}
          name={name}
          type={visible ? "text" : "password"}
          required={required}
          maxLength={72}
          autoComplete={autoComplete}
          aria-invalid={!!error}
          aria-describedby={error ? `${id}-error` : undefined}
          onChange={onChange}
          className="auth-input pr-12"
        />
        <button
          type="button"
          aria-label={`${visible ? "Hide" : "Show"} ${label.toLowerCase()}`}
          aria-pressed={visible}
          aria-controls={id}
          onClick={() => setVisible(!visible)}
          className="absolute inset-y-0 right-0 mt-2 flex items-center rounded-r-lg px-3 text-gray-500 hover:text-gray-900"
        >
          {visible ? (
            <EyeOff className="size-5" aria-hidden="true" />
          ) : (
            <Eye className="size-5" aria-hidden="true" />
          )}
        </button>
      </div>
      {error && (
        <p
          id={`${id}-error`}
          role="alert"
          className="mt-2 text-xs text-red-600"
        >
          {error}
        </p>
      )}
    </div>
  );
}
