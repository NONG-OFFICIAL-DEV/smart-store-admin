import { defineStore } from 'pinia'

// Cart state only — final authoritative totals always come back from the
// order-create response (server recomputes tax/discount/total), this store's
// `subtotal` is for on-screen display before submit only, never sent as-is.
export const usePosStore = defineStore('pos', {
  state: () => ({
    items: [],
    // each item: { key, product_id, product_unit_id, variant_id, name,
    //   variant_name, unit_label, unit_price, qty, notes, customizations,
    //   modifier_option_ids, custom_modifier_values } — the last three are
    //   food/coffee POS only, set by PosProductCustomizeDialog when the item
    //   has modifier selections. `key` already encodes size + every
    //   option/custom value + the note, so addItem()'s merge-by-key below
    //   only ever combines genuinely identical lines.

    // Food tenants only — dine_in/takeaway/delivery, table only applies to
    // dine_in. Customer is optional for either business type (walk-in sales
    // must keep working with none of this set). Defaults to dine_in so the
    // table picker is visible immediately on open — FoodPos.vue falls back
    // to the tenant's first enabled order type if dine_in isn't enabled.
    orderType: 'dine_in',
    tableId: null,
    customerId: null,
    customerName: null,

    // Order-level note (distinct from a per-item note) — optional, tenant-
    // configurable (Settings > POS > Order Notes).
    note: '',

    // A reward looked up via LoyaltyRedeemDialog.vue, not yet confirmed
    // server-side — { code, reward: {id, name, points_cost}, discount:
    // {type, amount|percent|free_product_id} }. Confirmed only once the
    // order this discount actually landed on is created (see submitOrder()
    // in FoodPos.vue/RetailPos.vue) — requesting it never re-deducts the
    // customer's points, that already happened when they tapped "Use now"
    // in the Mini App.
    redemption: null
  }),

  getters: {
    itemCount: state => state.items.reduce((sum, i) => sum + i.qty, 0),
    subtotal: state =>
      state.items.reduce((sum, i) => sum + i.unit_price * i.qty, 0),

    // What the redeemed reward is actually worth against this cart right
    // now — a free_item reward (or one with no monetary type configured)
    // has no numeric effect here; the cashier removes/comps that line
    // themselves before checkout.
    discountAmount() {
      if (!this.redemption?.discount) return 0
      const { type, amount, percent } = this.redemption.discount
      if (type === 'discount_amount') return Math.min(Number(amount) || 0, this.subtotal)
      if (type === 'discount_percent') {
        return Math.round(this.subtotal * (Number(percent) || 0)) / 100
      }
      return 0
    },

    payableTotal() {
      return Math.max(0, this.subtotal - this.discountAmount)
    }
  },

  actions: {
    addItem(item) {
      const existing = this.items.find(i => i.key === item.key)
      if (existing) {
        existing.qty += item.qty ?? 1
      } else {
        this.items.push({ qty: 1, notes: '', ...item })
      }
    },

    updateQty(key, qty) {
      const item = this.items.find(i => i.key === key)
      if (!item) return
      if (qty <= 0) {
        this.removeItem(key)
        return
      }
      item.qty = qty
    },

    updateNotes(key, notes) {
      const item = this.items.find(i => i.key === key)
      if (item) item.notes = notes
    },

    removeItem(key) {
      this.items = this.items.filter(i => i.key !== key)
    },

    setOrderType(orderType) {
      this.orderType = orderType
      // Table only makes sense for dine-in — drop it when switching away.
      if (orderType !== 'dine_in') this.tableId = null
    },

    setTable(tableId) {
      this.tableId = tableId
    },

    setCustomer(customerId, customerName = null) {
      this.customerId = customerId
      this.customerName = customerId ? customerName : null
      // A redeemed reward belongs to whoever was attached when it was
      // looked up — switching (or clearing) the customer drops it rather
      // than silently applying someone else's reward to this sale.
      this.redemption = null
    },

    setRedemption(redemption) {
      this.redemption = redemption
    },

    setNote(note) {
      this.note = note
    },

    clear() {
      this.items = []
      this.redemption = null
      this.orderType = 'dine_in'
      this.tableId = null
      this.customerId = null
      this.customerName = null
      this.note = ''
    }
  }
})
