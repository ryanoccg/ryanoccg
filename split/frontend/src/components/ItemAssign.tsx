import { useEffect, useRef, useState } from 'react'
import type { Member } from '../types'

/**
 * Per-item assignment: shows the people already on this item as compact chips,
 * plus an "Assign" button that opens a searchable checklist of the session's
 * people. Keeps each item row short no matter how many people are in the session.
 */
export default function ItemAssign({
  members,
  shares,
  onToggle,
  onSetWeight,
  onAssignAll,
}: {
  members: Member[]
  shares: Record<string, number>
  onToggle: (memberId: string) => void
  onSetWeight: (memberId: string, weight: string) => void
  onAssignAll: () => void
}) {
  const [open, setOpen] = useState(false)
  const [q, setQ] = useState('')
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    function onDoc(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onDoc)
    return () => document.removeEventListener('mousedown', onDoc)
  }, [])

  const assigned = members.filter((m) => shares[m.id])
  const multi = assigned.length > 1 // weights only matter when shared by 2+
  const term = q.trim().toLowerCase()
  const filtered = term ? members.filter((m) => m.display_name.toLowerCase().includes(term)) : members

  return (
    <div className="assign">
      {assigned.length === 0 && <span className="muted small">Unassigned</span>}
      {assigned.map((m) => (
        <span key={m.id} className="chip sm">
          {m.display_name}
          {multi && <span className="wlabel">×{shares[m.id]}</span>}
          <button type="button" className="chip-x" onClick={() => onToggle(m.id)} aria-label={`Remove ${m.display_name}`}>×</button>
        </span>
      ))}

      <div className="assign-picker" ref={ref}>
        <button type="button" className="pill" onClick={() => setOpen((o) => !o)}>+ Assign ▾</button>
        {open && (
          <div className="assign-menu">
            {members.length > 6 && (
              <input autoFocus placeholder="Search people…" value={q} onChange={(e) => setQ(e.target.value)} />
            )}
            <ul>
              {filtered.map((m) => {
                const on = !!shares[m.id]
                return (
                  <li key={m.id}>
                    <label>
                      <input type="checkbox" checked={on} onChange={() => onToggle(m.id)} />
                      <span className="grow">{m.display_name}</span>
                      {on && multi && (
                        <input
                          className="weight"
                          title="share weight"
                          inputMode="decimal"
                          value={String(shares[m.id])}
                          onClick={(e) => e.stopPropagation()}
                          onChange={(e) => onSetWeight(m.id, e.target.value)}
                        />
                      )}
                    </label>
                  </li>
                )
              })}
              {filtered.length === 0 && <li className="muted empty-opt">No matches</li>}
            </ul>
            {members.length > 0 && (
              <button type="button" className="linkbtn" onClick={onAssignAll}>Assign everyone</button>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
