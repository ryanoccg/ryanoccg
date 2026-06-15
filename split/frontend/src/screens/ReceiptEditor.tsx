import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, ApiError } from '../api'
import { useAuth } from '../auth/AuthContext'
import UpgradePrompt from '../components/UpgradePrompt'
import { CURRENCIES, formatCents, toCents, toNum } from '../money'
import type { Extraction, Member, Receipt, SessionDetail } from '../types'

interface EditItem {
  name: string
  quantity: string
  unit_price: string
  total: string
  shares: Record<string, number> // memberId -> weight
}

const blankItem = (): EditItem => ({ name: '', quantity: '1', unit_price: '', total: '', shares: {} })

export default function ReceiptEditor() {
  const { id, rid } = useParams()
  const sessionId = id ?? ''
  const receiptId = rid ?? ''
  const navigate = useNavigate()
  const { refreshPlan } = useAuth()

  const [members, setMembers] = useState<Member[]>([])
  const [currency, setCurrency] = useState('USD')
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
      // Auto-fill total from qty×unit only when it's blank — never clobber a
      // total the user (or OCR) already set.
      if (q && u && next.total.trim() === '') next.total = (q * u).toFixed(2)
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

  const addRow = () => setItems((p) => [...p, blankItem()])
  const removeRow = (i: number) => setItems((p) => p.filter((_, idx) => idx !== i))

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
        <div className="notice">Add members to the session first so you can assign items.</div>
      )}

      <section className="card">
        <label className="filebtn">
          {scanning ? 'Scanning…' : '📷 Scan a receipt photo (OCR)'}
          <input type="file" accept="image/*" capture="environment" onChange={onPickImage} disabled={scanning} hidden />
        </label>
        {confidence != null && (
          <div className="muted small">OCR confidence: {Math.round(confidence * 100)}%. Please verify the numbers.</div>
        )}
        <div className="muted small">Or just fill in the items manually below.</div>
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

        <h2>Items — assign each to who shared it</h2>
        {items.map((it, i) => (
          <div className="item" key={i}>
            <div className="item-fields">
              <input className="grow" placeholder="Item name" value={it.name} onChange={(e) => setItem(i, { name: e.target.value })} />
              <input className="num" placeholder="Qty" inputMode="decimal" value={it.quantity} onChange={(e) => updateField(i, 'quantity', e.target.value)} />
              <input className="num" placeholder="Unit" inputMode="decimal" value={it.unit_price} onChange={(e) => updateField(i, 'unit_price', e.target.value)} />
              <input className="num" placeholder="Total" inputMode="decimal" value={it.total} onChange={(e) => setItem(i, { total: e.target.value })} />
              <button className="chip-x" onClick={() => removeRow(i)} aria-label="Remove item">×</button>
            </div>
            <div className="assign">
              {members.map((m) => {
                const on = !!it.shares[m.id]
                return (
                  <span key={m.id} className="assign-member">
                    <button type="button" className={on ? 'pill on' : 'pill'} onClick={() => toggleShare(i, m.id)}>
                      {m.display_name}
                    </button>
                    {on && Object.keys(it.shares).length > 1 && (
                      <input className="weight" title="weight" inputMode="decimal"
                        value={String(it.shares[m.id])} onChange={(e) => setWeight(i, m.id, e.target.value)} />
                    )}
                  </span>
                )
              })}
              {members.length > 0 && (
                <button type="button" className="linkbtn" onClick={() => assignAll(i)}>everyone</button>
              )}
            </div>
          </div>
        ))}
        <button className="btn" onClick={addRow}>+ Add item</button>

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
