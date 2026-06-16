import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, ApiError } from '../api'
import { useAuth } from '../auth/AuthContext'
import UpgradePrompt from '../components/UpgradePrompt'
import ContactPicker from '../components/ContactPicker'
import ItemAssign from '../components/ItemAssign'
import { CURRENCIES, formatCents, toCents, toNum } from '../money'
import type { Extraction, Member, Receipt, SessionDetail } from '../types'

interface EditItem {
  name: string
  quantity: string
  unit_price: string
  total: string
  totalEdited?: boolean // true once total came from OCR/receipt or was hand-typed
  shares: Record<string, number> // memberId -> weight
}

const blankItem = (): EditItem => ({ name: '', quantity: '1', unit_price: '', total: '', totalEdited: false, shares: {} })

// Staged messages shown while the (single, opaque) OCR call runs — advance on a
// timer to feel alive. The last one sticks until the response returns.
const SCAN_STEPS = [
  'Uploading your receipt…',
  'AI is reading it…',
  'Listing the items…',
  'Wow, that’s a lot to eat! 😋',
  'Almost there…',
]

export default function ReceiptEditor() {
  const { id, rid } = useParams()
  const sessionId = id ?? ''
  const receiptId = rid ?? ''
  const navigate = useNavigate()
  const { refreshPlan } = useAuth()

  const [members, setMembers] = useState<Member[]>([])
  const [currency, setCurrency] = useState('MYR')
  const [merchant, setMerchant] = useState('')
  const [tax, setTax] = useState('')
  const [tip, setTip] = useState('')
  const [payerId, setPayerId] = useState<string>('')
  const [imagePath, setImagePath] = useState<string | null>(null)
  const [items, setItems] = useState<EditItem[]>([blankItem()])
  const [confidence, setConfidence] = useState<number | null>(null)

  const [loading, setLoading] = useState(true)
  const [scanning, setScanning] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [limitMsg, setLimitMsg] = useState('')
  const [scanStep, setScanStep] = useState(0)

  // Load session members (+ the receipt being edited).
  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await api.get(`sessions.php?id=${sessionId}`)
      const session: SessionDetail = res.session
      setMembers(session.members)
      setCurrency(session.currency)
      if (receiptId) {
        const r: Receipt | undefined = session.receipts.find((x) => x.id === receiptId)
        if (r) hydrateFromReceipt(r)
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not load.')
    } finally {
      setLoading(false)
    }
  }, [sessionId, receiptId])
  useEffect(() => { load() }, [load])

  // Advance the staged scan message while scanning.
  useEffect(() => {
    if (!scanning) { setScanStep(0); return }
    setScanStep(0)
    const id = setInterval(() => setScanStep((s) => Math.min(s + 1, SCAN_STEPS.length - 1)), 3000)
    return () => clearInterval(id)
  }, [scanning])

  // Refresh just the people list (after a quick-add) without touching the form.
  async function refreshMembers() {
    try {
      const res = await api.get(`members.php?session_id=${sessionId}`)
      setMembers(res.members)
    } catch {
      /* ignore */
    }
  }

  function hydrateFromReceipt(r: Receipt) {
    setMerchant(r.merchant || '')
    setCurrency(r.currency)
    setTax(String(toNum(r.tax) || ''))
    setTip(String(toNum(r.tip) || ''))
    setPayerId(r.paid_by_member_id ?? '')
    setImagePath(r.image_path)
    setItems(
      r.line_items.map((li) => ({
        name: li.name,
        quantity: String(toNum(li.quantity)),
        unit_price: String(toNum(li.unit_price)),
        total: String(toNum(li.total)),
        totalEdited: true,
        shares: Object.fromEntries(li.shares.map((s) => [s.member_id, Number(s.weight) || 1])),
      })),
    )
  }

  function applyExtraction(x: Extraction) {
    if (x.merchant) setMerchant(x.merchant)
    if (x.currency) setCurrency(x.currency)
    if (x.tax != null) setTax(String(x.tax))
    if (x.tip != null) setTip(String(x.tip))
    setImagePath(x.image_path)
    setConfidence(x.confidence)
    if (x.line_items.length) {
      setItems(x.line_items.map((li) => ({
        name: li.name,
        quantity: String(li.quantity),
        unit_price: String(li.unit_price),
        total: String(li.total),
        totalEdited: true,
        shares: {},
      })))
    }
  }

  async function onPickImage(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return
    setScanning(true); setError(''); setNotice(''); setLimitMsg('')
    try {
      const form = new FormData()
      form.append('session_id', String(sessionId))
      form.append('image', file)
      const x: Extraction = await api.postForm('extract_receipt.php', form)
      await refreshPlan()
      if (x.status === 'failed') {
        setImagePath(x.image_path)
        setNotice(x.message || 'Could not read the receipt — please enter the items manually.')
      } else {
        applyExtraction(x)
        setNotice('Scanned! Review and correct the items below, then assign people.')
      }
    } catch (err) {
      if (err instanceof ApiError && err.isLimit) setLimitMsg(err.message)
      else setError(err instanceof ApiError ? err.message : 'Scan failed.')
    } finally {
      setScanning(false)
      e.target.value = ''
    }
  }

  // ---- item helpers ----
  const setItem = (i: number, patch: Partial<EditItem>) =>
    setItems((prev) => prev.map((it, idx) => (idx === i ? { ...it, ...patch } : it)))

  function updateField(i: number, field: 'quantity' | 'unit_price', value: string) {
    setItems((prev) => prev.map((it, idx) => {
      if (idx !== i) return it
      const next = { ...it, [field]: value }
      const q = toNum(next.quantity), u = toNum(next.unit_price)
      // Recompute total from qty×unit live, unless the total was set by hand/OCR.
      if (q && u && !next.totalEdited) next.total = (q * u).toFixed(2)
      return next
    }))
  }

  function toggleShare(i: number, memberId: string) {
    setItems((prev) => prev.map((it, idx) => {
      if (idx !== i) return it
      const shares = { ...it.shares }
      if (shares[memberId]) delete shares[memberId]
      else shares[memberId] = 1
      return { ...it, shares }
    }))
  }

  const setWeight = (i: number, memberId: string, w: string) =>
    setItems((prev) => prev.map((it, idx) => {
      if (idx !== i) return it
      const shares = { ...it.shares, [memberId]: Math.max(0.001, toNum(w) || 1) }
      return { ...it, shares }
    }))

  function assignAll(i: number) {
    setItems((prev) => prev.map((it, idx) => {
      if (idx !== i) return it
      return { ...it, shares: Object.fromEntries(members.map((m) => [m.id, it.shares[m.id] || 1])) }
    }))
  }

  // Whole-receipt shortcut: everyone shares every item equally (shared meal).
  function shareAllEqually() {
    setItems((prev) => prev.map((it) => ({
      ...it,
      shares: Object.fromEntries(members.map((m) => [m.id, it.shares[m.id] || 1])),
    })))
  }
  function clearAllAssignments() {
    setItems((prev) => prev.map((it) => ({ ...it, shares: {} })))
  }

  const addRow = () => setItems((p) => [...p, blankItem()])
  const removeRow = (i: number) => setItems((p) => p.filter((_, idx) => idx !== i))

  // Add a person to the session straight from an item's assign menu, then assign
  // them to that item — no scrolling back up to the People section.
  async function addPersonToItem(i: number, name: string) {
    setError('')
    try {
      const res = await api.post('members.php', { session_id: sessionId, display_name: name })
      await refreshMembers()
      const memberId: string = res.member.id
      setItems((prev) => prev.map((it, idx) =>
        idx === i ? { ...it, shares: { ...it.shares, [memberId]: it.shares[memberId] || 1 } } : it,
      ))
    } catch (err) {
      if (err instanceof ApiError && err.isLimit) setLimitMsg(err.message)
      else setError(err instanceof ApiError ? err.message : 'Could not add person.')
    }
  }

  const itemsSubtotalCents = useMemo(
    () => items.reduce((s, it) => s + toCents(it.total), 0),
    [items],
  )
  const totalCents = itemsSubtotalCents + toCents(tax) + toCents(tip)

  async function save() {
    setError(''); setSaving(true)
    try {
      const payload = {
        session_id: sessionId,
        merchant: merchant || null,
        currency,
        subtotal: itemsSubtotalCents / 100,
        tax: toNum(tax),
        tip: toNum(tip),
        total: totalCents / 100,
        paid_by_member_id: payerId === '' ? null : payerId,
        image_path: imagePath,
        line_items: items
          .filter((it) => it.name.trim() && toNum(it.total) > 0)
          .map((it) => ({
            name: it.name.trim(),
            quantity: toNum(it.quantity) || 1,
            unit_price: toNum(it.unit_price),
            total: toNum(it.total),
            shares: Object.entries(it.shares).map(([mid, w]) => ({ member_id: mid, weight: w })),
          })),
      }
      if (payload.line_items.length === 0) {
        setError('Add at least one item with a total.')
        setSaving(false)
        return
      }
      if (receiptId) await api.put(`receipts.php?id=${receiptId}`, payload)
      else await api.post('receipts.php', payload)
      navigate(`/s/${sessionId}`)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not save receipt.')
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <div className="muted">Loading…</div>

  return (
    <div className="stack">
      <div className="breadcrumb"><Link to={`/s/${sessionId}`}>‹ Back to session</Link></div>
      <h1>{receiptId ? 'Edit receipt' : 'Add receipt'}</h1>

      {limitMsg && <UpgradePrompt message={limitMsg} onDismiss={() => setLimitMsg('')} />}
      {error && <div className="error">{error}</div>}
      {notice && <div className="notice">{notice}</div>}

      {members.length === 0 && (
        <div className="notice">Add the people who shared this receipt below — then tap each item to assign it.</div>
      )}

      <section className="card">
        <label className={scanning ? 'filebtn disabled' : 'filebtn'}>
          {scanning ? 'Scanning…' : '📷 Scan or upload a receipt photo (OCR)'}
          {/* No capture attr → lets the user take a photo OR pick an existing image/file. */}
          <input type="file" accept="image/*" onChange={onPickImage} disabled={scanning} hidden />
        </label>
        {scanning ? (
          <div className="scan-loading mt">
            <div className="scan-row">
              <span className="spinner" aria-hidden="true" />
              <span>{SCAN_STEPS[scanStep]}</span>
            </div>
            <div className="progress"><div className="progress-bar" /></div>
          </div>
        ) : (
          <div className="mt muted small">
            {confidence != null && (
              <div>OCR confidence: {Math.round(confidence * 100)}%. Please verify the numbers.</div>
            )}
            <div>Or just fill in the items manually below.</div>
          </div>
        )}
      </section>

      <section className="card form">
        <div className="form-row">
          <label className="grow">Place / merchant
            <input value={merchant} onChange={(e) => setMerchant(e.target.value)} placeholder="e.g. Sushi King" />
          </label>
          <label>Currency
            <select value={currency} onChange={(e) => setCurrency(e.target.value)} style={{ width: 90 }}>
              {Array.from(new Set([currency, ...CURRENCIES])).filter(Boolean).map((c) => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>
          </label>
        </div>

        <div className="totalbar">
          <span className="muted small">Receipt total</span>
          <span className="totalbar-amt">{formatCents(totalCents, currency)}</span>
        </div>

        <h2>People</h2>
        <div className="chips">
          {members.map((m) => <span className="chip" key={m.id}>{m.display_name}</span>)}
          {members.length === 0 && <span className="muted">No one added yet.</span>}
        </div>
        <div className="mt">
          <ContactPicker
            sessionId={sessionId}
            memberContactIds={new Set(members.map((m) => m.contact_id))}
            onAdded={() => refreshMembers()}
            onLimit={setLimitMsg}
          />
        </div>
        <h2>Items — assign each to who shared it</h2>
        {members.length > 0 && (
          <div className="item-actions">
            <button type="button" className="btn" onClick={shareAllEqually}>👥 Everyone shares all items</button>
            <button type="button" className="linkbtn" onClick={clearAllAssignments}>Clear all</button>
          </div>
        )}
        {items.map((it, i) => (
          <div className="item" key={i}>
            <div className="item-fields">
              <input className="item-name" placeholder="Item name" value={it.name} onChange={(e) => setItem(i, { name: e.target.value })} />
              <div className="item-nums">
                <label className="numfield"><span>Qty</span>
                  <input className="num" inputMode="decimal" value={it.quantity} onChange={(e) => updateField(i, 'quantity', e.target.value)} />
                </label>
                <label className="numfield"><span>Unit price</span>
                  <input className="num" inputMode="decimal" value={it.unit_price} onChange={(e) => updateField(i, 'unit_price', e.target.value)} />
                </label>
                <label className="numfield"><span>Total</span>
                  <input className="num" inputMode="decimal" value={it.total} onChange={(e) => setItem(i, { total: e.target.value, totalEdited: true })} />
                </label>
                <button className="chip-x" onClick={() => removeRow(i)} aria-label="Remove item">×</button>
              </div>
            </div>
            <ItemAssign
              members={members}
              shares={it.shares}
              onToggle={(mid) => toggleShare(i, mid)}
              onSetWeight={(mid, w) => setWeight(i, mid, w)}
              onAssignAll={() => assignAll(i)}
              onAddPerson={(name) => addPersonToItem(i, name)}
            />
          </div>
        ))}
        <button className="btn mt" onClick={addRow}>+ Add item</button>

        <div className="totals">
          <label>Tax <input className="num" inputMode="decimal" value={tax} onChange={(e) => setTax(e.target.value)} /></label>
          <label>Tip <input className="num" inputMode="decimal" value={tip} onChange={(e) => setTip(e.target.value)} /></label>
          <div className="grand">Total: {formatCents(totalCents, currency)}</div>
        </div>

        <div className="form-row">
          <label className="grow">Who paid?
            <select value={payerId} onChange={(e) => setPayerId(e.target.value)}>
              <option value="">— select payer —</option>
              {members.map((m) => <option key={m.id} value={m.id}>{m.display_name}</option>)}
            </select>
          </label>
        </div>

        <button className="btn btn-primary" onClick={save} disabled={saving}>
          {saving ? 'Saving…' : receiptId ? 'Save changes' : 'Save receipt'}
        </button>
      </section>
    </div>
  )
}
