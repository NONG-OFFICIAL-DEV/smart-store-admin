import { defineStore } from 'pinia'
import { getAllRefundsApi, getRefundByIdApi, createRefundApi } from '../api/refundService'

export const useRefundStore = defineStore('refund', {
  state: () => ({
    refunds:    [],
    refund:     null,
    pagination: {},
  }),

  actions: {
    async fetchRefunds(filters) {
      const res       = await getAllRefundsApi(filters)
      this.refunds    = res.data.data
      this.pagination = res.data.meta
    },
    async fetchRefundById(id) {
      const res     = await getRefundByIdApi(id)
      this.refund   = res.data.data
    },
    async createRefund(paymentId, data) {
      const res = await createRefundApi(paymentId, data)
      this.refunds.unshift(res.data.data)
    },
  },
})
