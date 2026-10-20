import { Navigate } from 'react-router'
import { useAuth } from '../../auth/context'

export default function AdminRedirect() {
  const {user, loading, error, refresh} = useAuth()
  if (loading) return <p role="status" className="p-8 text-sm text-gray-500">Checking your session…</p>
  if (error) return <div role="alert" className="p-8 text-sm"><p>{error}</p><button className="mt-3 underline" onClick={() => void refresh()}>Try again</button></div>
  return <Navigate to={user ? '/site-admin' : '/site-admin/login'} replace/>
}
