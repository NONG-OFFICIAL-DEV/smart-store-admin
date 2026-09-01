import { defineStore } from 'pinia'

// Cart state only — final authoritative totals always come back from the
// order-create response (server recomputes tax/discount/total), this store's
// `subtotal` is for on-screen display before submit only, never sent as-is.
export const usePosStore = defineStore('pos', {
  state: () => ({
    items: []
    // each item: { key, product_id, product_unit_id, variant_id, name,
    //   variant_name, unit_label, unit_price, qty, notes }
  }),

  getters: {
    itemCount: state => state.items.reduce((sum, i) => sum + i.qty, 0),
    subtotal: state =>
      state.items.reduce((sum, i) => sum + i.unit_price * i.qty, 0)
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

    clear() {
      this.items = []
    }
  }
})
