import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { ApiError } from '../api'

export default function Auth() {
  const { login, register } = useAuth()
  const navigate = useNavigate()
  const [mode, setMode] = useState<'login' | 'register'>('login')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setError('')
    setBusy(true)
    try {
      if (mode === 'login') await login(email, password)
      else await register(email, password)
      navigate('/')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Something went wrong.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="auth-wrap">
      <div className="auth-card">
        <h1 className="brand brand-lg">Splitwell</h1>
        <p className="muted">Split meal &amp; receipt costs — fairly, across many places.</p>

        <div className="tabs">
          <button className={mode === 'login' ? 'tab active' : 'tab'} onClick={() => setMode('login')}>Log in</button>
          <button className={mode === 'register' ? 'tab active' : 'tab'} onClick={() => setMode('register')}>Sign up</button>
        </div>

        <form onSubmit={submit} className="form">
          <label>Email
            <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="email" />
          </label>
          <label>Password
            <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8}
              autoComplete={mode === 'login' ? 'current-password' : 'new-password'} />
          </label>
          {error && <div className="error">{error}</div>}
          <button className="btn btn-primary" disabled={busy}>
            {busy ? 'Please wait…' : mode === 'login' ? 'Log in' : 'Create account'}
          </button>
        </form>
      </div>
    </div>
  )
}
