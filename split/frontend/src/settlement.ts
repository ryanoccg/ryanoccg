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
    const owedThisReceipt = receiptOwed(r, warnings)
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
function receiptOwed(r: Receipt, warnings: string[]): Map<string, number> {
  const itemSubtotal = new Map<string, number>() // cents
  let baseSubtotal = 0

  for (const item of r.line_items) {
    const itemCents = toCents(item.total)
    const shares = item.shares ?? []
    if (shares.length === 0) {
      if (itemCents > 0) warnings.push(`"${item.name}" isn't assigned to anyone.`)
      continue
    }
    let weightSum = 0
    for (const s of shares) weightSum += Math.max(0, Number(s.weight) || 0)
    if (weightSum <= 0) weightSum = shares.length

    // Distribute item cents by weight; push rounding remainder onto the last share.
    let allocated = 0
    shares.forEach((s, idx) => {
      const w = Math.max(0, Number(s.weight) || 0) || 1
      let portion = idx === shares.length - 1
        ? itemCents - allocated
        : Math.round((itemCents * w) / weightSum)
      allocated += portion
      itemSubtotal.set(s.member_id, (itemSubtotal.get(s.member_id) ?? 0) + portion)
      baseSubtotal += portion
    })
  }

  const taxTip = toCents(r.tax) + toCents(r.tip)
  const owed = new Map<string, number>()
  if (baseSubtotal <= 0) return owed

  let allocatedTaxTip = 0
  const entries = [...itemSubtotal.entries()]
  entries.forEach(([memberId, sub], idx) => {
    const extra = idx === entries.length - 1
      ? taxTip - allocatedTaxTip
      : Math.round((taxTip * sub) / baseSubtotal)
    allocatedTaxTip += extra
    owed.set(memberId, sub + extra)
  })
  return owed
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
