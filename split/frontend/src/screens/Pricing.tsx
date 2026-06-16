import { useState } from 'react'
import { api, ApiError } from '../api'
import { useAuth } from '../auth/AuthContext'

// Note: the hidden "Super" plan is intentionally NOT listed here. It is granted
// only via admin_set_plan.php and never appears in pricing.
export default function Pricing() {
  const { plan } = useAuth()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  async function upgrade() {
    setBusy(true); setError('')
    try {
      const res = await api.post('billing_checkout.php', {})
      window.location.href = res.url
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not start checkout.')
      setBusy(false)
    }
  }

  const current = plan?.plan

  return (
    <div className="stack">
      <h1>Pricing</h1>
      {error && <div className="error">{error}</div>}
      <div className="plans">
        <div className={`plan-card ${current === 'free' ? 'current' : ''}`}>
          <h2>Free</h2>
          <p className="price">$0</p>
          <ul>
            <li>2 sessions</li>
            <li>10 OCR scans / month</li>
            <li>Up to 5 members / session</li>
            <li>Includes ads</li>
          </ul>
          {current === 'free' && <div className="badge">Current plan</div>}
        </div>

        <div className={`plan-card featured ${current === 'pro' ? 'current' : ''}`}>
          <h2>Pro</h2>
          <p className="price">Subscription</p>
          <ul>
            <li>Unlimited sessions</li>
            <li>200 OCR scans / month</li>
            <li>Unlimited members</li>
            <li>No ads</li>
          </ul>
          {current === 'pro' ? (
            <div className="badge">Current plan</div>
          ) : current === 'super' ? (
            <div className="badge">You have unlimited access</div>
          ) : (
            <button className="btn btn-primary" onClick={upgrade} disabled={busy}>
              {busy ? 'Redirecting…' : 'Upgrade to Pro'}
            </button>
          )}
        </div>
      </div>
    </div>
  )
}
