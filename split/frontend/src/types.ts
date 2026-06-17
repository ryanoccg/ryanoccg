// Shared API types. Primary keys are UUID strings. DECIMAL columns arrive as
// strings from PDO, so numeric fields are `number | string` and coerced via money.ts.

export type Plan = 'free' | 'pro' | 'super'

export interface User {
  id: string
  email: string
  plan: Plan
}

export interface Contact {
  id: string
  display_name: string
}

export interface Member {
  id: string
  session_id?: string
  contact_id: string
  display_name: string
}

export interface ItemShare {
  id?: string
  member_id: string
  weight: number | string
}

export interface LineItem {
  id?: string
  receipt_id?: string
  name: string
  quantity: number | string
  unit_price: number | string
  total: number | string
  sort_order?: number
  shares: ItemShare[]
}

export interface Receipt {
  id: string
  session_id?: string
  merchant: string | null
  currency: string
  subtotal: number | string
  tax: number | string
  tip: number | string
  rounding: number | string
  total: number | string
  paid_by_member_id: string | null
  status: 'processing' | 'ready' | 'failed'
  image_path: string | null
  created_at?: string
  line_items: LineItem[]
}

export interface SessionSummary {
  id: string
  name: string
  currency: string
  created_at: string
  member_count: number | string
  receipt_count: number | string
}

export interface SessionDetail {
  id: string
  name: string
  currency: string
  owner_user_id?: string
  created_at?: string
  members: Member[]
  receipts: Receipt[]
}

export interface PlanInfo {
  user: User
  is_admin?: boolean
  plan: Plan
  plan_expires_at: string | null
  ads: boolean
  limits: { sessions: number; ocr_per_month: number; members_per_session: number }
  usage: { sessions: number; ocr_scans: number; ocr_tokens?: number; period: string }
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
  rounding: number | null
  total: number | null
  line_items: { name: string; quantity: number; unit_price: number; total: number }[]
  confidence: number | null
  message?: string
}
