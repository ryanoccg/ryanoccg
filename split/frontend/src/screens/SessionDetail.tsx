import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, ApiError } from '../api'
import { useAuth } from '../auth/AuthContext'
import UpgradePrompt from '../components/UpgradePrompt'
import { computeSettlement } from '../settlement'
import { formatCents, toCents } from '../money'
import type { Member, SessionDetail as Session } from '../types'

export default function SessionDetail() {
  const { id } = useParams()
  const sessionId = Number(id)
  const navigate = useNavigate()
  const { refreshPlan } = useAuth()
  const [session, setSession] = useState<Session | null>(null)
  const [loading, setLoading] = useState(true)
  const [newMember, setNewMember] = useState('')
  const [limitMsg, setLimitMsg] = useState('')
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await api.get(`sessions.php?id=${sessionId}`)
      setSession(res.session)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not load session.')
    } finally {
      setLoading(false)
    }
  }, [sessionId])
  useEffect(() => { load() }, [load])

  const settlement = useMemo(() => (session ? computeSettlement(session) : null), [session])
  const memberName = (mid: number | null) =>
    session?.members.find((m: Member) => m.id === mid)?.display_name ?? '—'

  async function addMember(e: React.FormEvent) {
    e.preventDefault()
    setError(''); setLimitMsg('')
    try {
      await api.post('members.php', { session_id: sessionId, display_name: newMember })
      setNewMember('')
      await load()
    } catch (err) {
      if (err instanceof ApiError && err.isLimit) setLimitMsg(err.message)
      else setError(err instanceof ApiError ? err.message : 'Could not add member.')
    }
  }

  async function removeMember(mid: number) {
    if (!confirm('Remove this member? Their item assignments will be cleared.')) return
    await api.del(`members.php?id=${mid}`)
    await load()
  }

  async function deleteReceipt(rid: number) {
    if (!confirm('Delete this receipt?')) return
    await api.del(`receipts.php?id=${rid}`)
    await load()
  }

  async function deleteSession() {
    if (!confirm('Delete this whole session and all its receipts?')) return
    await api.del(`sessions.php?id=${sessionId}`)
    await refreshPlan()
    navigate('/')
  }

  if (loading) return <div className="muted">Loading…</div>
  if (!session) return <div className="error">{error || 'Not found.'}</div>

  const currency = session.currency

  return (
    <div className="stack">
      <div className="breadcrumb"><Link to="/">‹ Sessions</Link></div>
      <div className="head-row">
        <h1>{session.name}</h1>
        <button className="linkbtn danger" onClick={deleteSession}>Delete session</button>
      </div>

      {limitMsg && <UpgradePrompt message={limitMsg} onDismiss={() => setLimitMsg('')} />}
      {error && <div className="error">{error}</div>}

      <section className="card">
        <h2>Members</h2>
        <div className="chips">
          {session.members.map((m) => (
            <span className="chip" key={m.id}>
              {m.display_name}
              <button className="chip-x" onClick={() => removeMember(m.id)} aria-label={`Remove ${m.display_name}`}>×</button>
            </span>
          ))}
          {session.members.length === 0 && <span className="muted">No members yet.</span>}
        </div>
        <form onSubmit={addMember} className="form-row mt">
          <input placeholder="Add member name" value={newMember} onChange={(e) => setNewMember(e.target.value)} required />
          <button className="btn">Add</button>
        </form>
      </section>

      <section className="card">
        <div className="head-row">
          <h2>Receipts</h2>
          <Link className="btn btn-primary" to={`/s/${sessionId}/add`}>+ Add receipt</Link>
        </div>
        {session.receipts.length === 0 ? (
          <div className="empty">No receipts yet. Add one — from any place — to start splitting.</div>
        ) : (
          <ul className="list">
            {session.receipts.map((r) => (
              <li key={r.id}>
                <div className="row-link">
                  <Link to={`/s/${sessionId}/receipt/${r.id}`} className="grow">
                    <div className="row-title">{r.merchant || 'Receipt'}</div>
                    <div className="muted small">
                      {formatCents(toCents(r.total), currency)} · paid by {memberName(r.paid_by_member_id)} · {r.line_items.length} items
                    </div>
                  </Link>
                  <button className="linkbtn danger" onClick={() => deleteReceipt(r.id)}>Delete</button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      {settlement && session.receipts.length > 0 && (
        <section className="card">
          <div className="head-row">
            <h2>Running balances</h2>
            <Link className="btn btn-primary" to={`/s/${sessionId}/settle`}>Compile &amp; settle up →</Link>
          </div>
          <ul className="balances">
            {settlement.balances.map((b) => (
              <li key={b.memberId}>
                <span>{b.name}</span>
                <span className={b.netCents > 0 ? 'pos' : b.netCents < 0 ? 'neg' : 'muted'}>
                  {b.netCents > 0 ? `is owed ${formatCents(b.netCents, currency)}`
                    : b.netCents < 0 ? `owes ${formatCents(-b.netCents, currency)}`
                    : 'settled'}
                </span>
              </li>
            ))}
          </ul>
          {settlement.warnings.length > 0 && (
            <ul className="warn-list">
              {settlement.warnings.map((w, i) => <li key={i}>⚠ {w}</li>)}
            </ul>
          )}
        </section>
      )}
    </div>
  )
}
