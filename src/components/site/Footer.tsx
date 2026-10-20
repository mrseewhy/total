import Logo from "../Logo";
import { contentModules } from "../../content/modules";
import { Link } from "react-router";

const Footer = () => {
  return (
    <footer className="bg-[#203c30] text-white">
      <div className="mx-auto grid max-w-7xl gap-8 px-6 py-12 sm:grid-cols-2 lg:px-10">
        <div>
          <Link to="/" className="text-3xl font-semibold tracking-tight">
            <Logo variant="light" />
          </Link>
          <p className="mt-3 max-w-xs text-sm leading-6 text-white/60">
            Good homes. Honest guidance.
            <br />A place to begin your next chapter.
          </p>
        </div>
        <div className="flex gap-10 sm:justify-end">
          <div>
            <p className="mb-4 text-xs font-medium text-white/50">EXPLORE</p>
            <div className="flex flex-col gap-3 text-sm">
              <Link to="/">Home</Link>
              <Link to="/about">About us</Link>
              {contentModules
                .filter((module) => module.navigation)
                .map((module) => (
                  <Link key={module.id} to={`/${module.path}`}>
                    {module.label}
                  </Link>
                ))}
              <Link to="/contact">Contact</Link>
            </div>
          </div>
          <div>
            <p className="mb-4 text-xs font-medium text-white/50">SAY HELLO</p>
            <Link to="/contact" className="text-sm">
              Talk to our team ↗
            </Link>
            <p className="mt-3 text-xs text-white/50">Lagos, Nigeria</p>
          </div>
        </div>
      </div>
      <div className="mx-auto flex max-w-7xl flex-wrap justify-between gap-3 border-t border-white/10 px-6 py-5 text-[11px] text-white/40 lg:px-10">
        <span>© {new Date().getFullYear()} Haven Real Estate</span>
        <span>Illustrative website · Sample properties</span>
      </div>
    </footer>
  );
};

export default Footer;
