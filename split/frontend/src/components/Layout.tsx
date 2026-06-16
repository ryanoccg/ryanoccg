import { useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import AdSlot from './AdSlot'

export default function Layout() {
  const { plan, logout } = useAuth()
  const navigate = useNavigate()
  const [menuOpen, setMenuOpen] = useState(false)
  const close = () => setMenuOpen(false)

  return (
    <div className="app">
      <header className="topbar">
        <Link to="/" className="brand" onClick={close}>Splitwell</Link>

        <button
          className="menu-toggle"
          onClick={() => setMenuOpen((o) => !o)}
          aria-label="Toggle menu"
          aria-expanded={menuOpen}
        >
          {menuOpen ? '✕' : '☰'}
        </button>

        <nav className={menuOpen ? 'nav open' : 'nav'}>
          <NavLink to="/" end onClick={close}>Sessions</NavLink>
          <NavLink to="/contacts" onClick={close}>Contacts</NavLink>
          <NavLink to="/pricing" onClick={close}>Pricing</NavLink>
          <NavLink to="/account" onClick={close}>Account</NavLink>
          <button className="linkbtn" onClick={() => { close(); logout(); navigate('/login') }}>
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
