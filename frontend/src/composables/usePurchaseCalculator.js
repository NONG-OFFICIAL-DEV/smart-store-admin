import { computed } from 'vue'

export function usePurchaseCalculator(purchase) {
  const itemTotals = computed(() =>
    purchase.items.map(item => {
      const price = item.cost_price || 0
      const qty = item.quantity || 0
      const discount = item.item_discount || 0
      const tax = item.item_tax || 0

      const subtotal = price * qty
      const discountAmount = subtotal * (discount / 100)
      const taxAmount = (subtotal - discountAmount) * (tax / 100)
      const total = subtotal - discountAmount + taxAmount

      return { subtotal, discountAmount, taxAmount, total }
    })
  )

  const subtotal = computed(() =>
    itemTotals.value.reduce((sum, i) => sum + i.subtotal, 0)
  )

  const totalDiscount = computed(() =>
    itemTotals.value.reduce((sum, i) => sum + i.discountAmount, 0)
  )

  const totalTax = computed(() =>
    itemTotals.value.reduce((sum, i) => sum + i.taxAmount, 0)
  )

  const totalAmount = computed(
    () => subtotal.value - totalDiscount.value + totalTax.value
  )

  return {
    itemTotals,
    subtotal,
    totalDiscount,
    totalTax,
    totalAmount
  }
}
