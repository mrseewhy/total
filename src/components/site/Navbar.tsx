import Logo from "../Logo";
import { contentModules } from "../../content/modules";
import { useEffect, useState } from "react";
import { Link, NavLink, useLocation } from "react-router";

const Navbar = () => {
  const [open, setOpen] = useState(false);
  const { pathname } = useLocation();
  useEffect(() => {
    window.scrollTo(0, 0);
  }, [pathname]);
  return (
    <header className="border-b border-stone-200 bg-[#faf9f6]">
      <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-6 lg:px-10">
        <Link
          to="/"
          className="flex items-center gap-2 text-2xl font-semibold tracking-tight"
        >
          <Logo />
        </Link>
        <button
          aria-label="Toggle navigation"
          aria-expanded={open}
          onClick={() => setOpen(!open)}
          className="rounded border border-stone-300 px-3 py-2 text-sm lg:hidden"
        >
          {open ? "Close" : "Menu"}
        </button>
        <nav
          aria-label="Main navigation"
          className={`${open ? "flex" : "hidden"} w-full flex-col gap-6 text-sm lg:flex lg:w-auto lg:flex-row lg:items-center lg:gap-4`}
        >
          {[
            { to: "/", label: "Home" },
            { to: "/about", label: "About us" },
            ...contentModules
              .filter((module) => module.navigation)
              .map((module) => ({
                to: `/${module.path}`,
                label: module.label,
              })),
            { to: "/contact", label: "Contact" },
          ].map((item) => (
            <NavLink
              end
              key={item.to}
              to={item.to}
              onClick={() => setOpen(false)}
              className={({ isActive }) =>
                isActive
                  ? "font-semibold text-[#294e3e] underline decoration-[#9b8768] underline-offset-8"
                  : "text-stone-500 hover:text-[#294e3e]"
              }
            >
              {item.label}
            </NavLink>
          ))}
          <Link
            onClick={() => setOpen(false)}
            to="/contact"
            className="rounded-lg bg-[#294e3e] px-5 py-3 text-center text-xs font-medium text-white"
          >
            Let's find your home ↗
          </Link>
        </nav>
      </div>
    </header>
  );
};

export default Navbar;
