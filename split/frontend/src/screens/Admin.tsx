import { useCallback, useEffect, useState } from 'react'
import { api, ApiError } from '../api'
import { useAuth } from '../auth/AuthContext'
import type { Plan } from '../types'

interface AdminUser {
  id: string
  email: string
  plan: Plan
  plan_expires_at: string | null
  is_admin: number
  created_at: string
  sessions: number
  ocr_scans: number
  ocr_tokens: number
}

interface AdminStats {
  period: string
  users: { total: number; free: number; pro: number; super: number }
  sessions: number
  receipts: number
  contacts: number
  this_month: { ocr_scans: number; ocr_tokens: number }
}

const PLANS: Plan[] = ['free', 'pro', 'super']

export default function Admin() {
  const { plan } = useAuth()
  const [stats, setStats] = useState<AdminStats | null>(null)
  const [users, setUsers] = useState<AdminUser[]>([])
  const [q, setQ] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  const loadUsers = useCallback(async (query = '') => {
    const res = await api.get(`admin_users.php${query ? `?q=${encodeURIComponent(query)}` : ''}`)
    setUsers(res.users)
  }, [])

  useEffect(() => {
    async function boot() {
      try {
        const [s] = await Promise.all([api.get('admin_stats.php'), loadUsers()])
        setStats(s)
      } catch (e) {
        setError(e instanceof ApiError ? e.message : 'Could not load admin data.')
      } finally {
        setLoading(false)
      }
    }
    boot()
  }, [loadUsers])

  // Debounced search.
  useEffect(() => {
    const t = setTimeout(() => { loadUsers(q).catch(() => {}) }, 250)
    return () => clearTimeout(t)
  }, [q, loadUsers])

  async function changePlan(u: AdminUser, next: Plan) {
    if (next === u.plan) return
    setError('')
    try {
      await api.put(`admin_users.php?id=${u.id}`, { plan: next })
      setUsers((prev) => prev.map((x) => (x.id === u.id ? { ...x, plan: next } : x)))
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Could not change plan.')
    }
  }

  if (plan && !plan.is_admin) {
    return <div className="stack"><h1>Admin</h1><div className="error">You don't have admin access.</div></div>
  }
  if (loading) return <div className="muted">Loading…</div>

  return (
    <div className="stack">
      <h1>Admin</h1>
      {error && <div className="error">{error}</div>}

      {stats && (
        <section className="card">
          <h2>Overview</h2>
          <div className="stat-grid">
            <div className="stat"><span className="stat-num">{stats.users.total}</span><span className="muted small">users</span></div>
            <div className="stat"><span className="stat-num">{stats.users.pro}</span><span className="muted small">pro</span></div>
            <div className="stat"><span className="stat-num">{stats.users.super}</span><span className="muted small">super</span></div>
            <div className="stat"><span className="stat-num">{stats.sessions}</span><span className="muted small">sessions</span></div>
            <div className="stat"><span className="stat-num">{stats.receipts}</span><span className="muted small">receipts</span></div>
            <div className="stat"><span className="stat-num">{stats.contacts}</span><span className="muted small">contacts</span></div>
          </div>
          <h3>This month ({stats.period})</h3>
          <ul className="usage">
            <li><span>OCR scans</span><span>{stats.this_month.ocr_scans.toLocaleString()}</span></li>
            <li><span>OCR tokens</span><span>{stats.this_month.ocr_tokens.toLocaleString()}</span></li>
          </ul>
        </section>
      )}

      <section className="card">
        <div className="head-row">
          <h2>Users ({users.length})</h2>
        </div>
        <input placeholder="🔍 Search by email…" value={q} onChange={(e) => setQ(e.target.value)} />
        <div className="table-wrap mt">
          <table className="admin-table">
            <thead>
              <tr><th>Email</th><th>Plan</th><th>Sessions</th><th>Scans</th><th>Tokens</th></tr>
            </thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id}>
                  <td>
                    {u.email}
                    {u.is_admin ? <span className="badge sm">admin</span> : null}
                  </td>
                  <td>
                    <select value={u.plan} onChange={(e) => changePlan(u, e.target.value as Plan)}>
                      {PLANS.map((p) => <option key={p} value={p}>{p}</option>)}
                    </select>
                  </td>
                  <td>{u.sessions}</td>
                  <td>{u.ocr_scans}</td>
                  <td>{u.ocr_tokens.toLocaleString()}</td>
                </tr>
              ))}
              {users.length === 0 && <tr><td colSpan={5} className="muted">No users match.</td></tr>}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  )
}
