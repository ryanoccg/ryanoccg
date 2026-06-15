import { useEffect, useRef } from 'react'

const CLIENT = import.meta.env.VITE_ADSENSE_CLIENT_ID as string | undefined

/**
 * Google AdSense slot — rendered only for Free-plan users (the caller gates on
 * plan.ads). If no client id is configured, shows a lightweight placeholder so
 * layout/dev still works. Requires a published /privacy + consent notice before
 * ads actually serve.
 */
export default function AdSlot({ slot }: { slot: string }) {
  const ref = useRef<HTMLModElement>(null)

  useEffect(() => {
    if (!CLIENT) return
    // Load the AdSense library once.
    const id = 'adsbygoogle-js'
    if (!document.getElementById(id)) {
      const s = document.createElement('script')
      s.id = id
      s.async = true
      s.crossOrigin = 'anonymous'
      s.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${CLIENT}`
      document.head.appendChild(s)
    }
    try {
      // @ts-expect-error adsbygoogle is injected by the external script
      ;(window.adsbygoogle = window.adsbygoogle || []).push({})
    } catch {
      /* ignore */
    }
  }, [])

  if (!CLIENT) {
    return <div className="ad-placeholder" aria-hidden="true">Ad · upgrade to Pro to remove</div>
  }

  return (
    <ins
      ref={ref}
      className="adsbygoogle ad-unit"
      style={{ display: 'block' }}
      data-ad-client={CLIENT}
      data-ad-slot={slot}
      data-ad-format="auto"
      data-full-width-responsive="true"
    />
  )
}
