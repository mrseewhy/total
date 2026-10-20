import { House } from "lucide-react";
export default function Logo({
  variant = "dark",
  className = "",
}: {
  variant?: "dark" | "light";
  className?: string;
}) {
  return (
    <span
      className={`inline-flex items-center gap-2 text-2xl font-semibold tracking-tight ${variant === "light" ? "text-white" : "text-gray-800"} ${className}`}
    >
      <span
        className={`flex size-8 items-center justify-center rounded-t-full ${variant === "light" ? "bg-white/15" : "bg-[#294e3e]"}`}
      >
        <House aria-hidden="true" className="size-5 text-white" />
      </span>
      Haven<span className="text-[#b49a70]">.</span>
    </span>
  );
}
