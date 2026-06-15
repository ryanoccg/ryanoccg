import { Link } from 'react-router-dom'

// A real privacy policy is required before Google AdSense will serve ads.
// This is a starting template — review with the actual data practices.
export default function Privacy() {
  return (
    <div className="stack legal">
      <div className="breadcrumb"><Link to="/">‹ Home</Link></div>
      <h1>Privacy Policy</h1>
      <p className="muted">Last updated: 2026</p>

      <h2>What we collect</h2>
      <p>
        When you create an account we store your email address and a securely hashed
        password. When you use the app we store the sessions, members, receipts, line
        items, and receipt images you create, so we can show them back to you across devices.
      </p>

      <h2>Receipt images &amp; OCR</h2>
      <p>
        Receipt photos you upload are stored privately on our server and are only
        accessible to your account. To read the items automatically, images are sent to
        our OCR provider (OpenAI) solely to extract the receipt contents.
      </p>

      <h2>Payments</h2>
      <p>
        Subscriptions are handled by Stripe. We do not store your card details; Stripe
        processes payment information under its own privacy policy.
      </p>

      <h2>Advertising &amp; cookies</h2>
      <p>
        On the Free plan we show ads via Google AdSense. Google and its partners may use
        cookies to serve ads based on your visits to this and other sites. You can opt out
        of personalized advertising in your Google Ad Settings. Pro and higher plans see no ads.
      </p>

      <h2>Data deletion</h2>
      <p>
        You can delete sessions and receipts at any time. To delete your account and all
        associated data, contact us.
      </p>

      <h2>Contact</h2>
      <p>For privacy questions, email the site owner.</p>
    </div>
  )
}
