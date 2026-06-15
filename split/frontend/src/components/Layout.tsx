import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import AdSlot from './AdSlot'

export default function Layout() {
  const { plan, logout } = useAuth()
  const navigate = useNavigate()

  return (
    <div className="app">
      <header className="topbar">
        <Link to="/" className="brand">Splitwell</Link>
        <nav className="nav">
          <NavLink to="/" end>Sessions</NavLink>
          <NavLink to="/pricing">Pricing</NavLink>
          <NavLink to="/account">Account</NavLink>
          <button className="linkbtn" onClick={() => { logout(); navigate('/login') }}>
            Log out
          </button>
        </nav>
      </header>

      {plan?.ads && <AdSlot slot="top" />}

      <main className="content">
        <Outlet />
      </main>

      <footer className="footer">
        <Link to="/privacy">Privacy</Link>
        <span className="muted"> · Splitwell</span>
      </footer>
    </div>
  )
}
