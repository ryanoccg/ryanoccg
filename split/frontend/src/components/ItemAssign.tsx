import { useEffect, useRef, useState } from 'react'
import type { Member } from '../types'

/**
 * Per-item assignment: shows the people already on this item as compact chips,
 * plus an "Assign" button that opens a searchable checklist of the session's
 * people. You can also add a brand-new contact right here (no scrolling back to
 * the top) — onAddPerson links/creates the contact and assigns it to this item.
 */
export default function ItemAssign({
  members,
  shares,
  onToggle,
  onSetWeight,
  onAssignAll,
  onAddPerson,
}: {
  members: Member[]
  shares: Record<string, number>
  onToggle: (memberId: string) => void
  onSetWeight: (memberId: string, weight: string) => void
  onAssignAll: () => void
  onAddPerson?: (name: string) => Promise<void>
}) {
  const [open, setOpen] = useState(false)
  const [q, setQ] = useState('')
  const [busy, setBusy] = useState(false)
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
  const exact = members.some((m) => m.display_name.toLowerCase() === term)
  const showCreate = !!onAddPerson && term !== '' && !exact

  async function addPerson() {
    if (!onAddPerson || term === '') return
    setBusy(true)
    try {
      await onAddPerson(q.trim())
      setQ('')
    } finally {
      setBusy(false)
    }
  }

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
            <input
              autoFocus
              placeholder="Search or add a person…"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              disabled={busy}
            />
            <div className="assign-pills">
              {filtered.map((m) => {
                const on = !!shares[m.id]
                return (
                  <span key={m.id} className="assign-pill-wrap">
                    <button type="button" className={on ? 'pill on' : 'pill'} onClick={() => onToggle(m.id)}>
                      {m.display_name}
                    </button>
                    {on && multi && (
                      <input
                        className="weight"
                        title="share weight"
                        inputMode="decimal"
                        value={String(shares[m.id])}
                        onChange={(e) => onSetWeight(m.id, e.target.value)}
                      />
                    )}
                  </span>
                )
              })}
              {filtered.length === 0 && !showCreate && <span className="muted empty-opt">No matches</span>}
            </div>
            {showCreate && (
              <button type="button" className="create-opt" onClick={addPerson} disabled={busy}>
                {busy ? 'Adding…' : `+ Add new contact “${q.trim()}”`}
              </button>
            )}
            {members.length > 0 && (
              <button type="button" className="linkbtn" onClick={onAssignAll}>Assign everyone</button>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
