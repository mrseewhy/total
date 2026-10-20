import Logo from "../Logo";
import { Link } from "react-router";
import type { ReactNode } from "react";
export default function AuthCard({
  title,
  description,
  children,
}: {
  title: string;
  description: string;
  children: ReactNode;
}) {
  return (
    <main className="flex min-h-screen items-center justify-center bg-gray-50 px-5 py-12">
      <div className="w-full max-w-md">
        <Link
          to="/"
          className="mb-8 block text-center text-3xl font-semibold text-gray-800"
        >
          <Logo />
        </Link>
        <section className="rounded-2xl border border-gray-200 bg-white p-7 shadow-sm sm:p-9">
          <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
          <p className="mt-3 mb-7 text-sm leading-6 text-gray-500">
            {description}
          </p>
          {children}
        </section>
        <Link
          to="/"
          target="_blank"
          rel="noopener noreferrer"
          className="mt-6 block text-center text-xs text-gray-500"
        >
          ← Visit website
        </Link>
      </div>
    </main>
  );
}
