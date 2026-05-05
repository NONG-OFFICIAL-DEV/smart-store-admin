// constants/businessTypes.js
//
// ─── THE ONLY PLACE you touch when adding a new business type ─────────────────
//
// Steps:
//   1. Add a row to the business_types DB table
//   2. Add the entry here — icon, color, and category
//   Done. authStore, useBusinessTypes, Operation.vue, sidebar all update automatically.

export const BUSINESS_TYPES = {
  // ── Food & Beverage ────────────────────────────────────────────────────────
  RESTAURANT:  { icon: 'mdi-silverware-fork-knife', color: 'deep-orange', category: 'food' },
  COFFEE_SHOP: { icon: 'mdi-coffee',                color: 'brown',       category: 'food' },
  BAKERY:      { icon: 'mdi-bread-slice-outline',   color: 'amber',       category: 'food' },
  KIOSK:       { icon: 'mdi-store-outline',         color: 'teal',        category: 'food' },
  FOOD_TRUCK:  { icon: 'mdi-truck-outline',         color: 'cyan',        category: 'food' },

  // ── Retail / Mart ──────────────────────────────────────────────────────────
  MART:        { icon: 'mdi-shopping-outline',      color: 'blue',        category: 'mart' },
  MINIMART:    { icon: 'mdi-shopping-outline',      color: 'blue',        category: 'mart' },
  RETAIL:      { icon: 'mdi-tag-outline',           color: 'indigo',      category: 'mart' },
  WHOLESALE:   { icon: 'mdi-warehouse',             color: 'purple',      category: 'mart' },

  // ── Add new types here ─────────────────────────────────────────────────────
  // PHARMACY: { icon: 'mdi-pill',             color: 'green', category: 'health' },
  // SALON:    { icon: 'mdi-content-cut',      color: 'pink',  category: 'beauty' },
}

// ── Derived helpers (auto-built, never edit manually) ─────────────────────────

// Set of all codes per category — used by authStore getters and Operation.vue
// { food: Set(['RESTAURANT', 'COFFEE_SHOP', ...]), mart: Set([...]) }
export const BU_CATEGORIES = Object.entries(BUSINESS_TYPES).reduce(
  (acc, [code, def]) => {
    if (!acc[def.category]) acc[def.category] = new Set()
    acc[def.category].add(code)
    return acc
  },
  {}
)

// Resolve display info by code — used by useBusinessTypes as fallback
export const resolveBuType = code =>
  BUSINESS_TYPES[code] ?? { icon: 'mdi-store-outline', color: 'grey', category: null }