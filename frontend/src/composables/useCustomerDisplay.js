import { ref, onMounted, onBeforeUnmount } from 'vue'
import {
  requestCustomerDisplayPairingApi,
  pushCustomerDisplayStateApi
} from '@/api/customerDisplayService'
import {
  connectCustomerDisplayEcho,
  disconnectCustomerDisplayEcho
} from '@/utils/customerDisplayEcho'

const PAIRING_PREFIX = 'customerDisplayPairing:' // + branchId
const DEVICE_KEY = 'customerDisplayDevice'

// The display has no authStore of its own (it's not logged in) — state
// payloads carry their own `currency` field instead, this formats against
// that directly rather than useCurrency_v2.js's authStore-driven version.
export function formatDisplayMoney(amount, currency) {
  const n = Number(amount) || 0
  return currency === 'KHR' ? `${Math.round(n).toLocaleString()} ៛` : `$${n.toFixed(2)}`
}

/**
 * Builds the ONE message shape the customer display understands — the POS
 * always sends the full state, never a diff, so a dropped push just means
 * the display shows last-known-good state until the next change.
 */
export function buildCustomerDisplayState({
  state,
  authStore,
  posStore,
  branchName,
  // Branch.customer_display_settings (see BranchResource) — per-branch,
  // not tenant-wide, since different locations want different WiFi text/
  // promos/QR images. Pass the active branch's own object; defaults to {}
  // so an unset branch (or one still on an old cached authStore.branches
  // read) degrades to "no promo, no WiFi line" rather than throwing.
  branchSettings = {},
  tableName = null,
  orderNumber = null,
  payment = null,
  totals = null
}) {
  const rate = Number(authStore.posSettings?.khr_exchange_rate) || 4100
  // Every stored/cart amount is in the TENANT'S OWN base currency, not
  // necessarily USD — same rule useCurrency_v2.js enforces for the POS's
  // own screen. A KHR-based tenant's `subtotal`/`totals.totalUsd` (despite
  // the param name — it's "total in base currency", not literally USD)
  // IS the riel figure already; converting it to totalUsd needs dividing
  // by the rate, not multiplying, or a KHR tenant's total was coming out
  // ~4100x too large (e.g. ៛10,000 shown as ៛41,000,000).
  const isKhrBase = (authStore.currency ?? 'USD') === 'KHR'
  const subtotal = posStore.subtotal
  const totalBase = totals?.totalUsd ?? subtotal
  const discount = totals?.discount ?? 0
  const totalUsd = isKhrBase ? totalBase / rate : totalBase
  const totalKhr = isKhrBase ? totalBase : totalBase * rate

  const cd = branchSettings ?? {}

  // Callers pass raw base-currency figures (paidBase/changeBase — straight
  // off the order's receipt, e.g. receiptData.cash_tendered) rather than
  // pre-converting themselves — this is the one place that knows
  // isKhrBase/rate, so every screen gets both paidUsd/paidKhr and
  // changeUsd/changeKhr regardless of the tenant's base currency.
  const toUsd = v => (v == null ? null : isKhrBase ? v / rate : v)
  const toKhr = v => (v == null ? null : Math.round(isKhrBase ? v : v * rate))

  let resolvedPayment = payment
  if (payment) {
    resolvedPayment = {
      ...payment,
      paidUsd: toUsd(payment.paidBase),
      paidKhr: toKhr(payment.paidBase),
      changeUsd: toUsd(payment.changeBase),
      changeKhr: toKhr(payment.changeBase)
    }
    delete resolvedPayment.paidBase
    delete resolvedPayment.changeBase

    // No real KHQR payload generation exists anywhere in this app — a QR
    // payment always falls back to the tenant's own static merchant QR
    // image (an actual photo/screenshot of their bank's QR, not a payload
    // to re-render) unless a caller explicitly supplies a real qrPayload.
    if (payment.method === 'qr') {
      resolvedPayment.qrPayload = payment.qrPayload ?? null
      resolvedPayment.qrImageUrl = payment.qrImageUrl ?? cd.merchant_qr_image_url ?? null
    }
  }

  return {
    state,
    branchName,
    logoUrl: authStore.logo_url ?? null,
    // Line items/subtotal/discount above are raw base-currency figures
    // (same ones useCurrency_v2.js formats for the POS's own screen) — the
    // screens need to know which currency that actually is to label them,
    // rather than re-deriving it from the converted totals.
    currency: authStore.currency ?? 'USD',
    tableName,
    orderNumber,
    items: posStore.items.map(i => ({
      qty: i.qty,
      name: i.name,
      nameKm: i.name_km ?? null,
      options: [i.variant_name ?? i.unit_label, i.notes].filter(Boolean).join(' · ') || null,
      lineTotal: Math.round(i.unit_price * i.qty * 100) / 100,
      imageUrl: i.image_url ?? null,
      isNew: false
    })),
    itemCount: posStore.itemCount,
    subtotal,
    discount,
    totalUsd: Math.round(totalUsd * 100) / 100,
    totalKhr: Math.round(totalKhr),
    payment: resolvedPayment,
    // Rotating poster images + Wi-Fi — sent on every state, not just idle,
    // so the display always has them ready without a separate fetch (the
    // poster carousel never stops, even mid-order/mid-payment).
    // branchSettings is reactive (a computed off the active branch), so
    // cd.posters is itself a reactive-wrapped array — spreading it into a
    // plain array is required, otherwise postMessage()'s structured clone
    // throws DataCloneError on the embedded Proxy.
    posters: Array.isArray(cd.posters) ? [...cd.posters] : [],
    wifiText: cd.wifi_text ?? null
  }
}

function pairingStorageKey(branchId) {
  return `${PAIRING_PREFIX}${branchId}`
}

/**
 * Publisher side — used by FoodPos.vue/RetailPos.vue. Always posts to the
 * same-PC BroadcastChannel (cheap, no-op if no window is listening); also
 * relays to a paired tablet via the backend if one's been claimed for the
 * active branch (see POS Settings > Customer display, Phase 3).
 */
export function useCustomerDisplayPublisher(branchIdRef) {
  const channel = new BroadcastChannel('customer-display')
  // BroadcastChannel never replays history — a display opened (or
  // reloaded) after the cart already changed would show nothing until the
  // NEXT change otherwise. It asks for a resend on mount; this resends
  // whatever was last published.
  let lastState = null
  channel.onmessage = e => {
    if (e.data?.type === 'request-state' && lastState) {
      channel.postMessage(lastState)
    }
  }

  function publish(state) {
    lastState = state
    channel.postMessage(state)

    const branchId = branchIdRef.value
    if (!branchId) return
    const raw = localStorage.getItem(pairingStorageKey(branchId))
    if (!raw) return

    const { deviceId } = JSON.parse(raw)
    pushCustomerDisplayStateApi(deviceId, state).catch(() => {
      // Best-effort — the "full state every time" contract self-heals on
      // the next cart change, no retry needed here.
    })
  }

  function pairDevice(deviceId) {
    const branchId = branchIdRef.value
    if (!branchId) return
    localStorage.setItem(pairingStorageKey(branchId), JSON.stringify({ deviceId }))
  }

  function unpairDevice() {
    const branchId = branchIdRef.value
    if (branchId) localStorage.removeItem(pairingStorageKey(branchId))
  }

  function hasPairedDevice() {
    const branchId = branchIdRef.value
    return branchId ? !!localStorage.getItem(pairingStorageKey(branchId)) : false
  }

  onBeforeUnmount(() => channel.close())

  return { publish, pairDevice, unpairDevice, hasPairedDevice }
}

/**
 * Receiver side — used only by CustomerDisplayPage.vue. Handles both
 * transports: same-PC always listens on the BroadcastChannel; if this
 * browser/device has a stored pairing, it also connects the realtime
 * channel. A screen can legitimately receive from either or both at once.
 */
export function useCustomerDisplayReceiver() {
  const mode = ref('loading') // loading | pairing | connected
  const pairingCode = ref(null)
  const connectionStatus = ref('connecting') // connecting | connected | reconnecting
  const displayState = ref({ state: 'idle' })
  // Same-PC (case A) never pairs at all — the moment ANY BroadcastChannel
  // message arrives, this display IS working, regardless of `mode`. Without
  // this, an unpaired display stays stuck showing the pairing code forever
  // even though the cashier's own POS tab is already broadcasting state.
  const receivedViaBroadcast = ref(false)

  let broadcastChannel = null

  function loadStoredDevice() {
    const raw = localStorage.getItem(DEVICE_KEY)
    return raw ? JSON.parse(raw) : null
  }

  function storeDevice(device) {
    localStorage.setItem(DEVICE_KEY, JSON.stringify(device))
  }

  function connectPrivateChannel(device) {
    mode.value = 'connected'
    disconnectCustomerDisplayEcho() // drop any token-less pairing connection first
    const echo = connectCustomerDisplayEcho(device.deviceToken)

    echo
      .private(`branch.${device.branchId}.terminal.${device.terminalId}.display`)
      .listen('.display.state', payload => {
        displayState.value = payload
      })

    echo.connector.pusher.connection.bind('state_change', states => {
      connectionStatus.value = states.current === 'connected' ? 'connected' : 'reconnecting'
    })
  }

  function startPairing() {
    mode.value = 'pairing'
    requestCustomerDisplayPairingApi().then(({ data }) => {
      const { device_session_id: deviceSessionId, pairing_code: code } = data.data
      pairingCode.value = code

      // Public channel — Echo's authorizer is only invoked for private/
      // presence channels, so a null device token here is fine.
      const echo = connectCustomerDisplayEcho(null)
      echo
        .channel(`customer-display-pairing.${deviceSessionId}`)
        .listen('.device.claimed', payload => {
          const device = {
            deviceSessionId,
            deviceToken: payload.device_token,
            branchId: payload.branch_id,
            terminalId: payload.terminal_id
          }
          storeDevice(device)
          connectPrivateChannel(device)
        })
    })
  }

  onMounted(() => {
    broadcastChannel = new BroadcastChannel('customer-display')
    broadcastChannel.onmessage = e => {
      // Another display tab's own request, not real state — ignore it.
      if (e.data?.type === 'request-state') return
      receivedViaBroadcast.value = true
      displayState.value = e.data
    }
    // Ask whatever POS tab is open to resend its current state — a
    // BroadcastChannel never replays history, so without this a display
    // opened (or reloaded) after the cart already changed shows nothing
    // until the next change.
    broadcastChannel.postMessage({ type: 'request-state' })

    const device = loadStoredDevice()
    if (device) {
      connectPrivateChannel(device)
    } else {
      startPairing()
    }
  })

  onBeforeUnmount(() => {
    broadcastChannel?.close()
    disconnectCustomerDisplayEcho()
  })

  return { mode, pairingCode, connectionStatus, displayState, receivedViaBroadcast }
}
