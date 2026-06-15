import { useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, ApiError } from '../api'
import { computeSettlement } from '../settlement'
import { formatCents } from '../money'
import type { SessionDetail } from '../types'

export default function Settlement() {
  const { id } = useParams()
  const sessionId = Number(id)
  const [session, setSession] = useState<SessionDetail | null>(null)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get(`sessions.php?id=${sessionId}`)
      .then((res) => setSession(res.session))
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Could not load.'))
  }, [sessionId])

  const result = useMemo(() => (session ? computeSettlement(session) : null), [session])

  if (error) return <div className="error">{error}</div>
  if (!session || !result) return <div className="muted">Loading…</div>

  const cur = session.currency

  return (
    <div className="stack">
      <div className="breadcrumb"><Link to={`/s/${sessionId}`}>‹ Back to session</Link></div>
      <h1>Settle up — {session.name}</h1>
      <p className="muted">Across {session.receipts.length} receipt(s) from every place in this session.</p>

      {result.warnings.length > 0 && (
        <ul className="warn-list">
          {result.warnings.map((w, i) => <li key={i}>⚠ {w}</li>)}
        </ul>
      )}

      <section className="card">
        <h2>Net balances</h2>
        <ul className="balances">
          {result.balances.map((b) => (
            <li key={b.memberId}>
              <span>{b.name}</span>
              <span className={b.netCents > 0 ? 'pos' : b.netCents < 0 ? 'neg' : 'muted'}>
                {b.netCents > 0 ? `should take back ${formatCents(b.netCents, cur)}`
                  : b.netCents < 0 ? `should pay out ${formatCents(-b.netCents, cur)}`
                  : 'settled'}
              </span>
            </li>
          ))}
        </ul>
      </section>

      <section className="card">
        <h2>Who pays whom</h2>
        {result.transactions.length === 0 ? (
          <div className="empty">Everyone's settled — no payments needed. 🎉</div>
        ) : (
          <ul className="settle-list">
            {result.transactions.map((t, i) => (
              <li key={i}>
                <strong>{t.fromName}</strong> pays <strong>{t.toName}</strong>
                <span className="amt">{formatCents(t.amountCents, cur)}</span>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}
