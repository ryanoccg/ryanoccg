import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { api, setToken, getToken } from '../api'
import type { PlanInfo, User } from '../types'

interface AuthState {
  user: User | null
  plan: PlanInfo | null
  loading: boolean
  login: (email: string, password: string) => Promise<void>
  register: (email: string, password: string) => Promise<void>
  logout: () => void
  refreshPlan: () => Promise<void>
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [plan, setPlan] = useState<PlanInfo | null>(null)
  const [loading, setLoading] = useState(true)

  async function refreshPlan() {
    try {
      const info: PlanInfo = await api.get('plan.php')
      setPlan(info)
      setUser((u) => (u ? { ...u, plan: info.plan } : u))
    } catch {
      /* ignore — keeps last known plan */
    }
  }

  // On boot, if a token exists, validate it by loading the plan/account.
  useEffect(() => {
    async function boot() {
      if (!getToken()) {
        setLoading(false)
        return
      }
      try {
        const info: PlanInfo = await api.get('plan.php')
        setPlan(info)
        // We don't persist the user object; reconstruct minimal identity.
        setUser({ id: 0, email: '', plan: info.plan })
      } catch {
        setToken(null)
      } finally {
        setLoading(false)
      }
    }
    boot()
  }, [])

  async function login(email: string, password: string) {
    const res = await api.post('login.php', { email, password })
    setToken(res.token)
    setUser(res.user)
    await refreshPlan()
  }

  async function register(email: string, password: string) {
    const res = await api.post('register.php', { email, password })
    setToken(res.token)
    setUser(res.user)
    await refreshPlan()
  }

  function logout() {
    setToken(null)
    setUser(null)
    setPlan(null)
  }

  return (
    <AuthContext.Provider value={{ user, plan, loading, login, register, logout, refreshPlan }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
