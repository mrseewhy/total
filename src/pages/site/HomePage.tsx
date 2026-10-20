import { Link } from "react-router";
import NewsletterSection from "../../components/site/NewsletterSection";
const properties = [
  {
    name: "The Courtyard Residence",
    area: "Lekki, Lagos",
    detail: "4 bedrooms · 4 bathrooms",
    image: "photo-1600596542815-ffad4c1539a9",
    type: "FOR SALE",
  },
  {
    name: "Parkside Apartment",
    area: "Ikoyi, Lagos",
    detail: "3 bedrooms · 3 bathrooms",
    image: "photo-1600607687920-4e2a09cf159d",
    type: "FOR RENT",
  },
  {
    name: "The Palm House",
    area: "Ajah, Lagos",
    detail: "4 bedrooms · 4 bathrooms",
    image: "photo-1600047509807-ba8f99d2cdde",
    type: "FOR SALE",
  },
];
export default function HomePage() {
  return (
    <main>
      <section className="mx-auto max-w-7xl px-6 pt-12 pb-8 lg:px-10">
        <div className="mb-10 grid items-end gap-6 md:grid-cols-[1.3fr_1fr]">
          <div>
            <p className="text-[11px] font-semibold tracking-[.2em] text-[#8b785b]">
              REAL ESTATE, WITH A HUMAN TOUCH
            </p>
            <h1 className="mt-5 font-serif text-5xl leading-[1.08] tracking-tight sm:text-6xl lg:text-7xl">
              More than a house.
              <br />
              <span className="italic text-[#6d806e]">A place to belong.</span>
            </h1>
          </div>
          <div className="md:pb-2 md:pl-12">
            <p className="max-w-sm text-sm leading-7 text-stone-500">
              Find a home that fits the life you want to live. Thoughtful
              spaces, local knowledge, and someone on your side.
            </p>
            <Link
              to="/contact"
              className="mt-6 inline-flex items-center gap-8 border-b border-[#294e3e] pb-2 text-sm font-medium"
            >
              Find your place <span>↗</span>
            </Link>
          </div>
        </div>
        <div className="relative overflow-hidden rounded-2xl bg-stone-200">
          <img
            src="https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=2000&q=85"
            alt="Contemporary home with generous windows, a garden, and an outdoor pool"
            fetchPriority="high"
            className="h-[350px] w-full object-cover sm:h-[470px]"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-black/45 to-transparent" />
          <div className="absolute right-5 bottom-5 left-5 flex items-end justify-between gap-4 text-white sm:right-8 sm:bottom-8 sm:left-8">
            <div>
              <p className="text-[10px] tracking-[.18em] text-white/75">
                SPACES THAT INSPIRE
              </p>
              <p className="mt-2 font-serif text-2xl sm:text-3xl">
                Room for the life you imagine.
              </p>
            </div>
            <a
              href="#homes"
              aria-label="Explore featured homes"
              className="flex size-11 shrink-0 items-center justify-center rounded-full border border-white/60 text-xl"
            >
              ↓
            </a>
          </div>
        </div>
        <div className="flex flex-wrap justify-between gap-4 border-b border-stone-200 py-6 text-[11px] text-stone-500">
          <span>Rooted in Lagos. Focused on you.</span>
          <span>
            BUY <span className="mx-3 text-stone-300">/</span> RENT{" "}
            <span className="mx-3 text-stone-300">/</span> SELL
          </span>
        </div>
      </section>
      <section id="homes" className="mx-auto max-w-7xl px-6 py-12 lg:px-10">
        <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
          <div>
            <p className="text-[11px] font-semibold tracking-[.2em] text-[#8b785b]">
              A FEW PLACES TO START
            </p>
            <h2 className="mt-3 font-serif text-4xl">
              Homes worth coming home to.
            </h2>
          </div>
          <span className="text-[10px] text-stone-400">
            Illustrative listings
          </span>
        </div>
        <div className="grid gap-7 md:grid-cols-3">
          {properties.map((property) => (
            <Link
              to={`/contact?property=${encodeURIComponent(property.name)}`}
              key={property.name}
              className="group"
            >
              <div className="relative overflow-hidden rounded-xl bg-stone-200">
                <img
                  loading="lazy"
                  src={`https://images.unsplash.com/${property.image}?auto=format&fit=crop&w=700&q=80`}
                  alt={property.name}
                  className="h-64 w-full object-cover transition duration-500 group-hover:scale-105"
                />
                <span className="absolute top-4 left-4 rounded bg-white/95 px-3 py-1.5 text-[9px] font-semibold tracking-wider">
                  {property.type}
                </span>
                <span className="absolute right-4 bottom-4 flex size-8 items-center justify-center rounded-full bg-white text-lg">
                  ↗
                </span>
              </div>
              <p className="mt-5 text-[10px] uppercase tracking-wider text-stone-400">
                {property.area}
              </p>
              <h3 className="mt-2 text-lg font-medium">{property.name}</h3>
              <p className="mt-2 text-xs text-stone-500">{property.detail}</p>
            </Link>
          ))}
        </div>
      </section>
      <section className="mt-10 bg-[#eeeae1]">
        <div className="mx-auto grid max-w-7xl gap-10 px-6 py-16 md:grid-cols-2 lg:px-10">
          <h2 className="font-serif text-4xl leading-tight">
            Finding a home is personal.
            <br />
            <span className="italic text-[#6d806e]">We keep it that way.</span>
          </h2>
          <div>
            <p className="max-w-lg text-sm leading-7 text-stone-600">
              We believe real estate should feel less complicated. We listen
              first, share honest advice, and help you make a decision that
              feels right for you.
            </p>
            <Link
              to="/about"
              className="mt-6 inline-block border-b border-stone-400 pb-2 text-sm font-medium"
            >
              Get to know Haven ↗
            </Link>
          </div>
        </div>
      </section>
      <NewsletterSection />
    </main>
  );
}
