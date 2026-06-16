import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './auth/AuthContext'
import Layout from './components/Layout'
import Auth from './screens/Auth'
import SessionList from './screens/SessionList'
import SessionDetail from './screens/SessionDetail'
import ReceiptEditor from './screens/ReceiptEditor'
import Settlement from './screens/Settlement'
import Contacts from './screens/Contacts'
import Pricing from './screens/Pricing'
import Account from './screens/Account'
import Privacy from './screens/Privacy'
import type { ReactNode } from 'react'

function Protected({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return <div className="center muted">Loading…</div>
  if (!user) return <Navigate to="/login" replace />
  return <>{children}</>
}

export default function App() {
  const { user } = useAuth()
  return (
    <Routes>
      <Route path="/login" element={user ? <Navigate to="/" replace /> : <Auth />} />
      <Route path="/privacy" element={<Privacy />} />
      <Route
        element={
          <Protected>
            <Layout />
          </Protected>
        }
      >
        <Route path="/" element={<SessionList />} />
        <Route path="/s/:id" element={<SessionDetail />} />
        <Route path="/s/:id/add" element={<ReceiptEditor />} />
        <Route path="/s/:id/receipt/:rid" element={<ReceiptEditor />} />
        <Route path="/s/:id/settle" element={<Settlement />} />
        <Route path="/contacts" element={<Contacts />} />
        <Route path="/pricing" element={<Pricing />} />
        <Route path="/account" element={<Account />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
