import NewsletterForm from "./NewsletterForm";

export default function NewsletterSection() {
  return (
    <section
      aria-labelledby="newsletter-title"
      className="mx-auto grid max-w-7xl items-center gap-10 px-6 py-20 lg:grid-cols-2 lg:gap-20 lg:px-10"
    >
      <div>
        <p className="text-[11px] font-semibold tracking-[.2em] text-[#8b785b]">
          THE HAVEN NEWSLETTER
        </p>
        <h2
          id="newsletter-title"
          className="mt-4 font-serif text-4xl leading-tight sm:text-5xl"
        >
          A little closer to
          <br />
          <span className="italic text-[#6d806e]">your next home.</span>
        </h2>
        <p className="mt-5 max-w-md text-sm leading-7 text-stone-500">
          New listings, neighbourhood insights, and a little inspiration for
          your next chapter. Delivered to your inbox.
        </p>
      </div>
      <div className="rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
        <h3 className="text-xl font-medium">Good things, in your inbox.</h3>
        <p className="mt-2 mb-6 text-sm leading-6 text-stone-500">
          Stay in the loop with the latest from Haven.
        </p>
        <NewsletterForm />
      </div>
    </section>
  );
}
