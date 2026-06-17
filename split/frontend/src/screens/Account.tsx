import { useEffect } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

export default function Account() {
  const { plan, refreshPlan } = useAuth()
  const [params] = useSearchParams()
  const justUpgraded = params.get('upgrade') === 'success'

  // After returning from Stripe Checkout, the webhook may have just flipped the
  // plan — refresh so the UI reflects it.
  useEffect(() => { refreshPlan() }, []) // eslint-disable-line react-hooks/exhaustive-deps

  if (!plan) return <div className="muted">Loading…</div>

  const unlimited = (n: number) => (n < 0 ? '∞' : n)

  return (
    <div className="stack">
      <h1>Account</h1>
      {justUpgraded && <div className="notice">Thanks for upgrading! Your Pro plan is now active.</div>}

      <section className="card">
        <h2>Plan: <span className="plan-name">{plan.plan}</span></h2>
        {plan.plan_expires_at && <p className="muted">Renews / expires: {plan.plan_expires_at}</p>}
        {plan.ads && <p className="muted">Free plan shows ads. Upgrade to remove them.</p>}

        <h3>This month ({plan.usage.period})</h3>
        <ul className="usage">
          <li>
            <span>OCR scans</span>
            <span>{plan.usage.ocr_scans} / {unlimited(plan.limits.ocr_per_month)}</span>
          </li>
          <li>
            <span>OCR tokens used</span>
            <span>{(plan.usage.ocr_tokens ?? 0).toLocaleString()}</span>
          </li>
          <li>
            <span>Sessions</span>
            <span>{plan.usage.sessions} / {unlimited(plan.limits.sessions)}</span>
          </li>
          <li>
            <span>People per session</span>
            <span>{unlimited(plan.limits.members_per_session)}</span>
          </li>
        </ul>
        <p className="muted small mt">
          Token usage is the OpenAI tokens spent on receipt scanning this month. For exact billing, see your{' '}
          <a href="https://platform.openai.com/usage" target="_blank" rel="noreferrer">OpenAI usage dashboard</a>.
        </p>

        {plan.plan === 'free' && (
          <div className="mt-lg">
            <Link className="btn btn-primary" to="/pricing">Upgrade to Pro</Link>
          </div>
        )}
      </section>
    </div>
  )
}
