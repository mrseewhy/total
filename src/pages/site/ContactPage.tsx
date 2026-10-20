import { useSearchParams } from 'react-router'
import ContactSection from '../../components/site/ContactSection'
export default function ContactPage() {
  const [params] = useSearchParams()
  const property = params.get('property')
  return <main><section className="mx-auto max-w-7xl border-b border-stone-200 px-6 pt-16 pb-12 lg:px-10"><p className="text-[11px] font-semibold tracking-[.2em] text-[#8b785b]">CONTACT HAVEN</p><h1 className="mt-5 font-serif text-5xl leading-tight sm:text-6xl">Let's find <span className="italic text-[#6d806e]">your place.</span></h1><p className="mt-5 max-w-xl text-sm leading-7 text-stone-500">Whether you know exactly what you want or you're just beginning to explore, we'd love to hear from you.</p>{property && <p className="mt-6 rounded-lg bg-[#eeeae1] px-4 py-3 text-sm">Interested in {property}? Include it in your message below.</p>}</section><ContactSection/></main>
}
