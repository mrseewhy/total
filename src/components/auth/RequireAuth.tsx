import { Navigate, Outlet, useLocation } from 'react-router'
import { useAuth } from '../../auth/context'
export default function RequireAuth() {
  const {user,loading,error,refresh}=useAuth()
  const location=useLocation()
  if(loading) return <p role="status" className="p-8 text-sm text-gray-500">Checking your session…</p>
  if(error) return <div role="alert" className="p-8 text-sm"><p>{error}</p><button className="mt-3 underline" onClick={()=>void refresh()}>Try again</button></div>
  if(!user) return <Navigate to="/site-admin/login" state={{from:location.pathname}} replace/>
  if(!['Admin','Manager'].includes(user.role)) return <p role="alert" className="p-8">You do not have access to this dashboard.</p>
  return <Outlet/>
}
