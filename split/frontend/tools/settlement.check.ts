// Ad-hoc verification of the real settlement.ts against hand-computed scenarios.
import { computeSettlement } from '../src/settlement'
import type { SessionDetail } from '../src/types'

let failures = 0
function eq(label: string, got: unknown, want: unknown) {
  const ok = JSON.stringify(got) === JSON.stringify(want)
  if (!ok) {
    failures++
    console.error(`FAIL ${label}: got ${JSON.stringify(got)} want ${JSON.stringify(want)}`)
  } else {
    console.log(`ok   ${label}`)
  }
}

// Members A, B, C (UUID-style string ids)
const members = [
  { id: 'A', display_name: 'A', contact_id: 'cA' },
  { id: 'B', display_name: 'B', contact_id: 'cB' },
  { id: 'C', display_name: 'C', contact_id: 'cC' },
]

function item(name: string, total: number, memberIds: string[], weights?: number[]) {
  return {
    name, quantity: 1, unit_price: total, total,
    shares: memberIds.map((m, i) => ({ member_id: m, weight: weights ? weights[i] : 1 })),
  }
}

// --- Scenario 1: two places, different payers, equal + partial shares ---
const s1: SessionDetail = {
  id: 's1', name: 'Trip', currency: 'USD', members,
  receipts: [
    { id: 'r1', merchant: 'Restaurant', currency: 'USD', subtotal: 42, tax: 0, tip: 0, total: 42,
      paid_by_member_id: 'A', status: 'ready', image_path: null,
      line_items: [item('Pizza', 30, ['A', 'B', 'C']), item('Beer', 12, ['A', 'B'])] },
    { id: 'r2', merchant: 'Taxi', currency: 'USD', subtotal: 30, tax: 0, tip: 0, total: 30,
      paid_by_member_id: 'B', status: 'ready', image_path: null,
      line_items: [item('Ride', 30, ['A', 'B', 'C'])] },
  ],
}
const r1 = computeSettlement(s1)
const net1 = Object.fromEntries(r1.balances.map((b) => [b.name, b.netCents]))
eq('S1 net A', net1['A'], 1600)
eq('S1 net B', net1['B'], 400)
eq('S1 net C', net1['C'], -2000)
eq('S1 sum zero', r1.balances.reduce((s, b) => s + b.netCents, 0), 0)
// C should pay A 16 and B 4 (2 transactions, total 20)
eq('S1 txn count', r1.transactions.length, 2)
eq('S1 txn total', r1.transactions.reduce((s, t) => s + t.amountCents, 0), 2000)
eq('S1 all from C', r1.transactions.every((t) => t.fromName === 'C'), true)

// --- Scenario 2: proportional tax+tip allocation ---
const s2: SessionDetail = {
  id: 's2', name: 'Dinner', currency: 'USD', members: members.slice(0, 2),
  receipts: [
    { id: 'r3', merchant: 'Cafe', currency: 'USD', subtotal: 40, tax: 5, tip: 3, total: 48,
      paid_by_member_id: 'A', status: 'ready', image_path: null,
      line_items: [item('Salad', 10, ['A']), item('Steak', 30, ['B'])] },
  ],
}
const r2 = computeSettlement(s2)
const net2 = Object.fromEntries(r2.balances.map((b) => [b.name, b.netCents]))
// A owes 10 + 8*(10/40)=2 → 12 ; B owes 30 + 8*(30/40)=6 → 36. A paid 48.
// net A = 48 - 12 = 36 ; net B = -36
eq('S2 net A', net2['A'], 3600)
eq('S2 net B', net2['B'], -3600)
eq('S2 B pays A 36', r2.transactions, [{ fromMemberId: 'B', fromName: 'B', toMemberId: 'A', toName: 'A', amountCents: 3600 }])

// --- Scenario 3: weighted share (A weight 2, B weight 1 on a 30 item) ---
const s3: SessionDetail = {
  id: 's3', name: 'Weighted', currency: 'USD', members: members.slice(0, 2),
  receipts: [
    { id: 'r4', merchant: 'Bar', currency: 'USD', subtotal: 30, tax: 0, tip: 0, total: 30,
      paid_by_member_id: 'B', status: 'ready', image_path: null,
      line_items: [item('Bottle', 30, ['A', 'B'], [2, 1])] },
  ],
}
const r3 = computeSettlement(s3)
const net3 = Object.fromEntries(r3.balances.map((b) => [b.name, b.netCents]))
// A owes 20, B owes 10; B paid 30 → net B = +20, net A = -20
eq('S3 net A', net3['A'], -2000)
eq('S3 net B', net3['B'], 2000)

// --- Scenario 4: unassigned item + no-payer warnings ---
const s4: SessionDetail = {
  id: 's4', name: 'Warn', currency: 'USD', members: members.slice(0, 2),
  receipts: [
    { id: 'r5', merchant: 'Shop', currency: 'USD', subtotal: 20, tax: 0, tip: 0, total: 20,
      paid_by_member_id: 'A', status: 'ready', image_path: null,
      line_items: [item('Assigned', 10, ['B']), { name: 'Lonely', quantity: 1, unit_price: 10, total: 10, shares: [] }] },
  ],
}
const r4 = computeSettlement(s4)
// Unassigned "Lonely" now defaults to the payer (A) — no warning.
eq('S4 no unassigned warning', r4.warnings.some((w) => w.includes('Lonely')), false)
const net4 = Object.fromEntries(r4.balances.map((b) => [b.name, b.netCents]))
// B owes its $10; the unassigned $10 falls to payer A. A paid 20, owes 10 → net +10; B -10.
eq('S4 net A', net4['A'], 1000)
eq('S4 net B', net4['B'], -1000)
eq('S4 payer A absorbs unassigned', r4.balances.find((b) => b.name === 'A')!.owedCents, 1000)

// --- Scenario 5: an ASSIGNED item but NO payer → receipt excluded with a warning ---
const s5: SessionDetail = {
  id: 's5', name: 'NoPayer', currency: 'USD', members: members.slice(0, 2),
  receipts: [
    { id: 'r6', merchant: 'Stall', currency: 'USD', subtotal: 10, tax: 0, tip: 0, total: 10,
      paid_by_member_id: null, status: 'ready', image_path: null,
      line_items: [item('Shared', 10, ['B'])] },
  ],
}
const r5 = computeSettlement(s5)
eq('S5 no-payer warning', r5.warnings.some((w) => w.toLowerCase().includes('payer')), true)
eq('S5 nothing owed', r5.balances.every((b) => b.netCents === 0), true)

// --- Scenario 6: equal split rounds fairly (leftover cents don't pile on one) ---
const s6: SessionDetail = {
  id: 's6', name: 'Even', currency: 'USD', members,
  receipts: [
    { id: 'r7', merchant: 'Mamak', currency: 'USD', subtotal: 2, tax: 0, tip: 0, total: 2,
      paid_by_member_id: 'A', status: 'ready', image_path: null,
      // two RM1 items each shared by all three → 200c / 3 ≈ 66.67 each
      line_items: [item('x', 1, ['A', 'B', 'C']), item('y', 1, ['A', 'B', 'C'])] },
  ],
}
const r6 = computeSettlement(s6)
const owed6 = r6.balances.map((b) => b.owedCents)
eq('S6 owed sums to 200', owed6.reduce((a, b) => a + b, 0), 200)
eq('S6 spread within 1c', Math.max(...owed6) - Math.min(...owed6) <= 1, true)

console.log(failures === 0 ? '\nALL PASSED' : `\n${failures} FAILED`)
process.exit(failures === 0 ? 0 : 1)
