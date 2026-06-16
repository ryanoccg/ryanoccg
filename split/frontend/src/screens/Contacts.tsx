import { useEffect, useState } from 'react'
import { api, ApiError } from '../api'
import type { Contact } from '../types'

// Your account-level address book. Contacts are reusable across every session.
// "Delete" is a soft delete — the contact is hidden here but past sessions keep
// showing the name.
export default function Contacts() {
  const [contacts, setContacts] = useState<Contact[]>([])
  const [q, setQ] = useState('')
  const [name, setName] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  async function load(query = '') {
    setLoading(true)
    try {
      const res = await api.get(`contacts.php${query ? `?q=${encodeURIComponent(query)}` : ''}`)
      setContacts(res.contacts)
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Could not load contacts.')
    } finally {
      setLoading(false)
    }
  }

  // Debounced search.
  useEffect(() => {
    const t = setTimeout(() => load(q), 250)
    return () => clearTimeout(t)
  }, [q])

  async function add(e: React.FormEvent) {
    e.preventDefault()
    if (!name.trim()) return
    setError('')
    try {
      await api.post('contacts.php', { display_name: name.trim() })
      setName('')
      await load(q)
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Could not add contact.')
    }
  }

  async function rename(c: Contact) {
    const next = prompt('Rename contact (updates them in every session):', c.display_name)
    if (!next || !next.trim() || next.trim() === c.display_name) return
    try {
      await api.put(`contacts.php?id=${c.id}`, { display_name: next.trim() })
      await load(q)
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Could not rename contact.')
    }
  }

  async function remove(c: Contact) {
    if (!confirm(`Delete "${c.display_name}"? They'll be hidden from your contacts but stay in past sessions.`)) return
    try {
      await api.del(`contacts.php?id=${c.id}`)
      await load(q)
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Could not delete contact.')
    }
  }

  return (
    <div className="stack">
      <h1>Contacts</h1>
      <p className="muted">People you split with. Add them once and reuse them in any session.</p>
      {error && <div className="error">{error}</div>}

      <form onSubmit={add} className="card form-row">
        <input className="grow" placeholder="New contact name" value={name} onChange={(e) => setName(e.target.value)} required />
        <button className="btn btn-primary">Add contact</button>
      </form>

      <input placeholder="🔍 Search contacts…" value={q} onChange={(e) => setQ(e.target.value)} />

      {loading ? (
        <div className="muted">Loading…</div>
      ) : contacts.length === 0 ? (
        <div className="empty">{q ? 'No contacts match.' : 'No contacts yet. Add one above.'}</div>
      ) : (
        <ul className="list">
          {contacts.map((c) => (
            <li key={c.id}>
              <div className="row-link">
                <span className="grow row-title">{c.display_name}</span>
                <button className="linkbtn" onClick={() => rename(c)}>Rename</button>
                <button className="linkbtn danger" onClick={() => remove(c)}>Delete</button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
