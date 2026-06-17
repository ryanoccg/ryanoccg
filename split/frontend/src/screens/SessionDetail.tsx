import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, ApiError, imageUrl } from '../api'
import { useAuth } from '../auth/AuthContext'
import UpgradePrompt from '../components/UpgradePrompt'
import ContactPicker from '../components/ContactPicker'
import { computeSettlement } from '../settlement'
import { formatCents, toCents } from '../money'
import type { Member, SessionDetail as Session } from '../types'

export default function SessionDetail() {
  const { id } = useParams()
  const sessionId = id ?? ''
  const navigate = useNavigate()
  const { refreshPlan } = useAuth()
  const [session, setSession] = useState<Session | null>(null)
  const [loading, setLoading] = useState(true)
  const [limitMsg, setLimitMsg] = useState('')
  const [error, setError] = useState('')
  const [lightbox, setLightbox] = useState<string | null>(null)

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
  const memberName = (mid: string | null) =>
    session?.members.find((m: Member) => m.id === mid)?.display_name ?? '—'

  async function removeMember(mid: string) {
    if (!confirm('Remove this member? Their item assignments will be cleared.')) return
    setError('')
    try {
      await api.del(`members.php?id=${mid}`)
      await load()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not remove member.')
    }
  }

  async function deleteReceipt(rid: string) {
    if (!confirm('Delete this receipt?')) return
    setError('')
    try {
      await api.del(`receipts.php?id=${rid}`)
      await load()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not delete receipt.')
    }
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
        <h2>People in this session</h2>
        <div className="chips">
          {session.members.map((m) => (
            <span className="chip" key={m.id}>
              {m.display_name}
              <button className="chip-x" onClick={() => removeMember(m.id)} aria-label={`Remove ${m.display_name}`}>×</button>
            </span>
          ))}
          {session.members.length === 0 && <span className="muted">No one yet.</span>}
        </div>
        <div className="mt">
          <ContactPicker
            sessionId={sessionId}
            memberContactIds={new Set(session.members.map((m) => m.contact_id))}
            onAdded={() => load()}
            onLimit={setLimitMsg}
          />
        </div>
        <p className="muted small mt">Contacts are saved to your account and can be reused in any session.</p>
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
                  {r.image_path && (
                    <img className="receipt-thumb" src={imageUrl(r.id)} alt="receipt"
                      onClick={() => setLightbox(imageUrl(r.id))} />
                  )}
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

      {lightbox && (
        <div className="lightbox" onClick={() => setLightbox(null)}>
          <img src={lightbox} alt="Receipt" />
          <button className="lightbox-close" aria-label="Close">×</button>
        </div>
      )}
    </div>
  )
}
