// Shared API types. DECIMAL columns arrive as strings from PDO, so numeric
// fields are typed as `number | string` and coerced via money.ts helpers.

export type Plan = 'free' | 'pro' | 'super'

export interface User {
  id: number
  email: string
  plan: Plan
}

export interface Member {
  id: number
  session_id?: number
  display_name: string
  linked_user_id: number | null
}

export interface ItemShare {
  id?: number
  member_id: number
  weight: number | string
}

export interface LineItem {
  id?: number
  receipt_id?: number
  name: string
  quantity: number | string
  unit_price: number | string
  total: number | string
  sort_order?: number
  shares: ItemShare[]
}

export interface Receipt {
  id: number
  session_id?: number
  merchant: string | null
  currency: string
  subtotal: number | string
  tax: number | string
  tip: number | string
  total: number | string
  paid_by_member_id: number | null
  status: 'processing' | 'ready' | 'failed'
  image_path: string | null
  created_at?: string
  line_items: LineItem[]
}

export interface SessionSummary {
  id: number
  name: string
  currency: string
  created_at: string
  member_count: number | string
  receipt_count: number | string
}

export interface SessionDetail {
  id: number
  name: string
  currency: string
  owner_user_id?: number
  created_at?: string
  members: Member[]
  receipts: Receipt[]
}

export interface PlanInfo {
  plan: Plan
  plan_expires_at: string | null
  ads: boolean
  limits: { sessions: number; ocr_per_month: number; members_per_session: number }
  usage: { sessions: number; ocr_scans: number; period: string }
}

// OCR extraction result used to pre-fill the review screen.
export interface Extraction {
  status: 'ready' | 'failed'
  image_path: string | null
  merchant: string | null
  currency: string | null
  subtotal: number | null
  tax: number | null
  tip: number | null
  total: number | null
  line_items: { name: string; quantity: number; unit_price: number; total: number }[]
  confidence: number | null
  message?: string
}
