import { useEffect, useRef, useState } from 'react'
import { api, ApiError } from '../api'
import type { Contact, Member } from '../types'

/**
 * Add a person to a session: search the account-level contacts (reused across
 * every session) or quick-add a brand-new contact by typing a name. Calls
 * onAdded with the created/linked member.
 */
export default function ContactPicker({
  sessionId,
  memberContactIds,
  onAdded,
  onLimit,
  placeholder = 'Add person — search contacts or type a new name',
}: {
  sessionId: string
  memberContactIds: Set<string>
  onAdded: (m: Member) => void
  onLimit?: (msg: string) => void
  placeholder?: string
}) {
  const [q, setQ] = useState('')
  const [results, setResults] = useState<Contact[]>([])
  const [open, setOpen] = useState(false)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')
  const boxRef = useRef<HTMLDivElement>(null)

  // Debounced search of the contact book.
  useEffect(() => {
    let active = true
    const t = setTimeout(async () => {
      try {
        const res = await api.get(`contacts.php?q=${encodeURIComponent(q.trim())}`)
        if (active) setResults(res.contacts)
      } catch {
        /* ignore search errors */
      }
    }, 200)
    return () => { active = false; clearTimeout(t) }
  }, [q])

  // Close the menu on outside click.
  useEffect(() => {
    function onDoc(e: MouseEvent) {
      if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onDoc)
    return () => document.removeEventListener('mousedown', onDoc)
  }, [])

  async function add(payload: { contact_id?: string; display_name?: string }) {
    setBusy(true)
    setErr('')
    try {
      const res = await api.post('members.php', { session_id: sessionId, ...payload })
      onAdded(res.member)
      setQ('')
      setResults([])
      setOpen(false)
    } catch (e) {
      if (e instanceof ApiError && e.isLimit) onLimit?.(e.message)
      else setErr(e instanceof ApiError ? e.message : 'Could not add person.')
    } finally {
      setBusy(false)
    }
  }

  const term = q.trim().toLowerCase()
  const available = results.filter((c) => !memberContactIds.has(c.id))
  const exact = results.some((c) => c.display_name.toLowerCase() === term)
  const showCreate = term !== '' && !exact

  return (
    <div className="picker" ref={boxRef}>
      <input
        placeholder={placeholder}
        value={q}
        onChange={(e) => { setQ(e.target.value); setOpen(true) }}
        onFocus={() => setOpen(true)}
        disabled={busy}
      />
      {open && (available.length > 0 || showCreate) && (
        <ul className="picker-menu">
          {available.map((c) => (
            <li key={c.id}>
              <button type="button" onClick={() => add({ contact_id: c.id })}>{c.display_name}</button>
            </li>
          ))}
          {showCreate && (
            <li>
              <button type="button" className="create" onClick={() => add({ display_name: q.trim() })}>
                + Add new contact “{q.trim()}”
              </button>
            </li>
          )}
        </ul>
      )}
      {err && <div className="error small">{err}</div>}
    </div>
  )
}
