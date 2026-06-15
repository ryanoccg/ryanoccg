// Thin fetch client. Adds the JWT, parses JSON, and raises ApiError (carrying
// HTTP status + optional `code` so callers can detect 402 limit_reached).

const API_BASE = (import.meta.env.VITE_API_BASE as string) || '/api'

export class ApiError extends Error {
  status: number
  code?: string
  action?: string
  constructor(message: string, status: number, code?: string, action?: string) {
    super(message)
    this.status = status
    this.code = code
    this.action = action
  }
  get isLimit(): boolean {
    return this.status === 402 || this.code === 'limit_reached'
  }
}

let token: string | null = localStorage.getItem('split_token')

export function getToken(): string | null {
  return token
}

export function setToken(t: string | null): void {
  token = t
  if (t) localStorage.setItem('split_token', t)
  else localStorage.removeItem('split_token')
}

async function parse(res: Response): Promise<any> {
  const text = await res.text()
  let data: any = null
  if (text) {
    try {
      data = JSON.parse(text)
    } catch {
      data = { error: text }
    }
  }
  if (!res.ok) {
    throw new ApiError(data?.error || `Request failed (${res.status})`, res.status, data?.code, data?.action)
  }
  return data
}

function authHeaders(extra: Record<string, string> = {}): Record<string, string> {
  const h: Record<string, string> = { ...extra }
  if (token) h['Authorization'] = `Bearer ${token}`
  return h
}

export const api = {
  base: API_BASE,

  get: (path: string) => fetch(`${API_BASE}/${path}`, { headers: authHeaders() }).then(parse),

  post: (path: string, body: unknown) =>
    fetch(`${API_BASE}/${path}`, {
      method: 'POST',
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(body ?? {}),
    }).then(parse),

  put: (path: string, body: unknown) =>
    fetch(`${API_BASE}/${path}`, {
      method: 'PUT',
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(body ?? {}),
    }).then(parse),

  del: (path: string) =>
    fetch(`${API_BASE}/${path}`, { method: 'DELETE', headers: authHeaders() }).then(parse),

  // Multipart upload (OCR). Do NOT set Content-Type — the browser adds the boundary.
  postForm: (path: string, form: FormData) =>
    fetch(`${API_BASE}/${path}`, { method: 'POST', headers: authHeaders(), body: form }).then(parse),
}

/** URL for an authenticated receipt image (token in query so <img> can load it). */
export function imageUrl(receiptId: number): string {
  return `${API_BASE}/image.php?receipt_id=${receiptId}&token=${encodeURIComponent(token || '')}`
}
