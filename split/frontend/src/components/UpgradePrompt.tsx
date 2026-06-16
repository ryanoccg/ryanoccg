import { Link } from 'react-router-dom'

/** Friendly "limit reached → upgrade" banner shown when a guard returns 402. */
export default function UpgradePrompt({ message, onDismiss }: { message: string; onDismiss?: () => void }) {
  return (
    <div className="upgrade" role="alert">
      <div>
        <strong>Plan limit reached.</strong> <span>{message}</span>
      </div>
      <div className="upgrade-actions">
        <Link className="btn btn-primary" to="/pricing">Upgrade to Pro</Link>
        {onDismiss && <button className="linkbtn" onClick={onDismiss}>Dismiss</button>}
      </div>
    </div>
  )
}
