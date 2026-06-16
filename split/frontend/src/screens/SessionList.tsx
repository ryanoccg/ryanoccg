import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../api'
import { useAuth } from '../auth/AuthContext'
import UpgradePrompt from '../components/UpgradePrompt'
import { CURRENCIES } from '../money'
import type { SessionSummary } from '../types'

export default function SessionList() {
  const { refreshPlan } = useAuth()
  const [sessions, setSessions] = useState<SessionSummary[]>([])
  const [loading, setLoading] = useState(true)
  const [name, setName] = useState('')
  const [currency, setCurrency] = useState('MYR')
  const [membersText, setMembersText] = useState('')
  const [error, setError] = useState('')
  const [limitMsg, setLimitMsg] = useState('')
  const [busy, setBusy] = useState(false)

  async function load() {
    setLoading(true)
    try {
      const res = await api.get('sessions.php')
      setSessions(res.sessions)
    } finally {
      setLoading(false)
    }
  }
  useEffect(() => { load() }, [])

  async function create(e: React.FormEvent) {
    e.preventDefault()
    setError('')
    setLimitMsg('')
    setBusy(true)
    try {
      const members = membersText.split(/[,\n]/).map((s) => s.trim()).filter(Boolean)
      await api.post('sessions.php', { name, currency, members })
      setName(''); setMembersText('')
      await load()
      await refreshPlan()
    } catch (err) {
      if (err instanceof ApiError && err.isLimit) setLimitMsg(err.message)
      else setError(err instanceof ApiError ? err.message : 'Could not create session.')
    } finally {
      setBusy(false)
    }
  }

  async function remove(s: SessionSummary) {
    if (!confirm(`Delete "${s.name}" and all its receipts and photos?`)) return
    setError('')
    try {
      await api.del(`sessions.php?id=${s.id}`)
      await load()
      await refreshPlan()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not delete session.')
    }
  }

  return (
    <div className="stack">
      <h1>Your sessions</h1>
      <p className="muted">A session groups all the receipts from one outing or trip — even across different places.</p>

      {limitMsg && <UpgradePrompt message={limitMsg} onDismiss={() => setLimitMsg('')} />}

      <form onSubmit={create} className="card form-row">
        <input placeholder="Session name (e.g. Penang trip)" value={name} onChange={(e) => setName(e.target.value)} required />
        <select value={currency} onChange={(e) => setCurrency(e.target.value)}>
          {CURRENCIES.map((c) => <option key={c} value={c}>{c}</option>)}
        </select>
        <input placeholder="Members (comma separated)" value={membersText} onChange={(e) => setMembersText(e.target.value)} />
        <button className="btn btn-primary" disabled={busy}>{busy ? 'Creating…' : 'Create'}</button>
      </form>
      {error && <div className="error">{error}</div>}

      {loading ? (
        <div className="muted">Loading…</div>
      ) : sessions.length === 0 ? (
        <div className="empty">No sessions yet. Create one above to get started.</div>
      ) : (
        <ul className="list">
          {sessions.map((s) => (
            <li key={s.id}>
              <div className="row-link">
                <Link to={`/s/${s.id}`} className="grow">
                  <div className="row-title">{s.name}</div>
                  <div className="muted small">{s.member_count} people · {s.receipt_count} receipts · {s.currency}</div>
                </Link>
                <button className="linkbtn danger" onClick={() => remove(s)}>Delete</button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
