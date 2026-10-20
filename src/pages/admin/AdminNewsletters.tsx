import { useEffect, useState } from 'react'
import NewsletterSubscribersTable from '../../components/admin/NewsletterSubscribersTable'
import { fetchSubscribers } from '../../api/subscribers'
import type { NewsletterSubscriber, Pagination } from '../../types/subscriber'

export default function AdminNewsletters() {
  const [page, setPage] = useState(1)
  const [result, setResult] = useState<{data: NewsletterSubscriber[]; pagination: Pagination} | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [retry, setRetry] = useState(0)
  useEffect(() => {
    const controller = new AbortController()
    fetchSubscribers(page, controller.signal).then(setResult).catch(error => {
      if (!controller.signal.aborted) setError(error instanceof Error ? error.message : 'Could not load subscribers')
    }).finally(() => { if (!controller.signal.aborted) setLoading(false) })
    return () => controller.abort()
  }, [page, retry])
  if (error) return <div role="alert" className="mt-8 rounded-xl border border-red-200 bg-white p-6 text-sm"><p>{error}</p><button onClick={() => { setLoading(true); setError(''); setRetry(retry + 1) }} className="mt-3 font-medium underline">Try again</button></div>
  if (!result) return <p role="status" className="mt-8 text-sm text-gray-500">Loading subscribers…</p>
  return <NewsletterSubscribersTable subscribers={result.data} pagination={result.pagination} loading={loading} onPageChange={nextPage => { if (nextPage !== page) { setLoading(true); setError(''); setPage(nextPage) } }}/>
}
