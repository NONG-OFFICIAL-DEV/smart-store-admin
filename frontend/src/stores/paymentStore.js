import { defineStore } from 'pinia'
import { getAllPaymentsApi, getPaymentByIdApi, createPaymentApi, updatePaymentApi, deletePaymentApi } from '../api/paymentService'

export const usePaymentStore = defineStore('payment', {
  state: () => ({
    payments:   [],
    payment:    null,
    pagination: {},
  }),

  actions: {
    async fetchPayments(filters) {
      const res       = await getAllPaymentsApi(filters)
      this.payments   = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchPaymentById(id) {
      const res      = await getPaymentByIdApi(id)
      this.payment   = res.data.data
    },
    async createPayment(data) {
      const res = await createPaymentApi(data)
      this.payments.unshift(res.data.data)
    },
    async updatePayment(id, data) {
      const res   = await updatePaymentApi(id, data)
      const index = this.payments.findIndex(item => item.id === id)
      if (index !== -1) this.payments[index] = res.data.data
    },
    async deletePayment(id) {
      await deletePaymentApi(id)
      this.payments = this.payments.filter(item => item.id !== id)
    },
  },
})