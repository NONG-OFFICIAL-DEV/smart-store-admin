// constants/businessTypes.js
//
// Purely cosmetic fallback (icon/color) for business types not yet covered
// here — the actual food/mart classification is backend-driven now (see
// business_types.category, exposed as authStore.bu_category /
// authStore.isFood / authStore.isMart) so adding a new business type via
// Manage Business Types needs no frontend change to be classified correctly.
// Only touch this file to give a code a nicer icon/color than the generic
// default below.

export const BUSINESS_TYPES = {
  // ── Food & Beverage ────────────────────────────────────────────────────────
  RESTAURANT:  { icon: 'mdi-silverware-fork-knife', color: 'deep-orange' },
  COFFEE_SHOP: { icon: 'mdi-coffee',                color: 'brown' },
  BAKERY:      { icon: 'mdi-bread-slice-outline',   color: 'amber' },
  KIOSK:       { icon: 'mdi-store-outline',         color: 'teal' },
  FOOD_TRUCK:  { icon: 'mdi-truck-outline',         color: 'cyan' },

  // ── Retail / Mart ──────────────────────────────────────────────────────────
  MART:        { icon: 'mdi-shopping-outline',      color: 'blue' },
  MINIMART:    { icon: 'mdi-shopping-outline',      color: 'blue' },
  RETAIL:      { icon: 'mdi-tag-outline',           color: 'indigo' },
  WHOLESALE:   { icon: 'mdi-warehouse',             color: 'purple' },
}

// Resolve display info by code — used by useBusinessTypes as fallback
export const resolveBuType = code =>
  BUSINESS_TYPES[code] ?? { icon: 'mdi-store-outline', color: 'grey' }