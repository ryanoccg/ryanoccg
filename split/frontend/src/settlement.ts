// Settlement: compute per-member balances from a session, then reduce to a
// near-minimal "who pays whom" list. All math in integer cents.

import type { Receipt, SessionDetail } from './types'
import { toCents } from './money'

export interface Balance {
  memberId: string
  name: string
  paidCents: number // total reimbursable amount this member fronted
  owedCents: number // total this member consumed
  netCents: number // paid - owed (>0 ⇒ is owed money; <0 ⇒ owes)
}

export interface Transaction {
  fromMemberId: string
  fromName: string
  toMemberId: string
  toName: string
  amountCents: number
}

export interface SettlementResult {
  balances: Balance[]
  transactions: Transaction[]
  warnings: string[]
}

export interface ReceiptBreakdown {
  receiptId: string
  merchant: string | null
  totalCents: number
  hasPayer: boolean
  payerName: string
  lines: { memberId: string; name: string; cents: number }[]
}

/** What each person consumed on each individual receipt (for the expandable per-receipt view). */
export function receiptBreakdowns(session: SessionDetail): ReceiptBreakdown[] {
  const nameOf = new Map(session.members.map((m) => [m.id, m.display_name]))
  return session.receipts.map((r) => {
    const owed = receiptOwed(r, r.paid_by_member_id ?? null)
    const lines = [...owed.entries()]
      .map(([memberId, cents]) => ({ memberId, name: nameOf.get(memberId) ?? '—', cents }))
      .filter((l) => l.cents !== 0)
      .sort((a, b) => b.cents - a.cents)
    return {
      receiptId: r.id,
      merchant: r.merchant,
      totalCents: [...owed.values()].reduce((a, b) => a + b, 0),
      hasPayer: r.paid_by_member_id != null,
      payerName: r.paid_by_member_id ? (nameOf.get(r.paid_by_member_id) ?? '—') : '',
      lines,
    }
  })
}

/**
 * Per receipt: each member owes their weighted share of every assigned line
 * item, plus tax+tip allocated proportionally to that item subtotal. The payer
 * is credited exactly the sum others owe, so balances always net to zero.
 * Items with no shares, or receipts with no payer, are surfaced as warnings.
 */
export function computeSettlement(session: SessionDetail): SettlementResult {
  const members = session.members
  const paid = new Map<string, number>()
  const owed = new Map<string, number>()
  const warnings: string[] = []
  for (const m of members) {
    paid.set(m.id, 0)
    owed.set(m.id, 0)
  }

  for (const r of session.receipts) {
    const owedThisReceipt = receiptOwed(r, r.paid_by_member_id ?? null)
    let receiptTotal = 0
    for (const [memberId, cents] of owedThisReceipt) {
      owed.set(memberId, (owed.get(memberId) ?? 0) + cents)
      receiptTotal += cents
    }
    if (receiptTotal === 0) continue
    if (r.paid_by_member_id == null) {
      warnings.push(`"${r.merchant || 'Receipt'}" has no payer set — not included in settlement.`)
      // Roll back the owed we added, so this receipt is fully excluded.
      for (const [memberId, cents] of owedThisReceipt) {
        owed.set(memberId, (owed.get(memberId) ?? 0) - cents)
      }
      continue
    }
    paid.set(r.paid_by_member_id, (paid.get(r.paid_by_member_id) ?? 0) + receiptTotal)
  }

  const balances: Balance[] = members.map((m) => {
    const p = paid.get(m.id) ?? 0
    const o = owed.get(m.id) ?? 0
    return { memberId: m.id, name: m.display_name, paidCents: p, owedCents: o, netCents: p - o }
  })

  return { balances, transactions: minCashFlow(balances), warnings }
}

/** owed-cents-per-member for a single receipt (items + proportional tax/tip). */
function receiptOwed(r: Receipt, payerId: string | null): Map<string, number> {
  // Accumulate each member's EXACT (fractional) cents across all items, then round
  // ONCE per member at the end. This makes an equal split come out exactly even
  // (RM120 over 4 → RM30.00 each), instead of drifting from per-item rounding.
  const exact = new Map<string, number>()
  const add = (id: string, v: number) => exact.set(id, (exact.get(id) ?? 0) + v)
  let itemSubtotal = 0 // exact cents of all charged items (tax/tip base)

  for (const item of r.line_items) {
    const itemCents = toCents(item.total)
    if (itemCents <= 0) continue
    const shares = item.shares ?? []
    if (shares.length === 0) {
      // Unassigned items default to the payer — they cover whatever nobody claimed.
      if (payerId != null) {
        add(payerId, itemCents)
        itemSubtotal += itemCents
      }
      continue
    }
    let weightSum = 0
    for (const s of shares) weightSum += Math.max(0, Number(s.weight) || 0)
    if (weightSum <= 0) weightSum = shares.length
    for (const s of shares) {
      const w = Math.max(0, Number(s.weight) || 0) || 1
      add(s.member_id, (itemCents * w) / weightSum)
    }
    itemSubtotal += itemCents
  }

  // Tax + tip + rounding (can be negative), proportional to each member's item subtotal.
  const adjustments = toCents(r.tax) + toCents(r.tip) + toCents(r.rounding)
  if (itemSubtotal > 0 && adjustments !== 0) {
    for (const [id, sub] of [...exact.entries()]) {
      add(id, (adjustments * sub) / itemSubtotal)
    }
  }

  return roundExactToCents(exact)
}

/**
 * Round each member's fractional cents to integers via the largest-remainder
 * method, so the parts sum EXACTLY to the integer total (no drift, no leftover
 * always landing on the same person).
 */
function roundExactToCents(exact: Map<string, number>): Map<string, number> {
  const ids = [...exact.keys()]
  const vals = ids.map((id) => exact.get(id) as number)
  const target = Math.round(vals.reduce((a, b) => a + b, 0))
  const res = vals.map((v) => Math.floor(v))
  let rem = target - res.reduce((a, b) => a + b, 0)
  const order = vals
    .map((v, i) => ({ i, frac: v - Math.floor(v) }))
    .sort((a, b) => b.frac - a.frac || a.i - b.i)
  for (let k = 0; rem > 0 && order.length > 0; k++, rem--) {
    res[order[k % order.length].i] += 1
  }
  const out = new Map<string, number>()
  ids.forEach((id, i) => out.set(id, res[i]))
  return out
}

/**
 * Greedy min-cash-flow: repeatedly settle between the largest creditor and the
 * largest debtor. Near-optimal and produces few transactions.
 */
export function minCashFlow(balances: Balance[]): Transaction[] {
  const nameOf = new Map<string, string>()
  const net: { id: string; amt: number }[] = []
  for (const b of balances) {
    nameOf.set(b.memberId, b.name)
    if (b.netCents !== 0) net.push({ id: b.memberId, amt: b.netCents })
  }

  const txns: Transaction[] = []
  // Loop until everyone is within a cent of zero.
  for (let guard = 0; guard < 10000; guard++) {
    net.sort((a, b) => a.amt - b.amt)
    const debtor = net[0]
    const creditor = net[net.length - 1]
    if (!debtor || !creditor) break
    if (creditor.amt <= 0 || debtor.amt >= 0) break

    const amount = Math.min(creditor.amt, -debtor.amt)
    if (amount <= 0) break
    txns.push({
      fromMemberId: debtor.id,
      fromName: nameOf.get(debtor.id) ?? '',
      toMemberId: creditor.id,
      toName: nameOf.get(creditor.id) ?? '',
      amountCents: amount,
    })
    debtor.amt += amount
    creditor.amt -= amount
    // Drop settled (~0) parties.
    for (let i = net.length - 1; i >= 0; i--) {
      if (Math.abs(net[i].amt) === 0) net.splice(i, 1)
    }
  }
  return txns
}
