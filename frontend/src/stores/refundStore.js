import { defineStore } from 'pinia'
import { getAllRefundsApi, getRefundByIdApi, createRefundApi, updateRefundApi, deleteRefundApi } from '../api/refundService'

export const useRefundStore = defineStore('refund', {
  state: () => ({
    refunds:    [],
    refund:     null,
    pagination: {},
  }),

  actions: {
    async fetchRefunds(filters) {
      const res       = await getAllRefundsApi(filters)
      this.refunds    = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchRefundById(id) {
      const res     = await getRefundByIdApi(id)
      this.refund   = res.data.data
    },
    async createRefund(data) {
      const res = await createRefundApi(data)
      this.refunds.unshift(res.data.data)
    },
    async updateRefund(id, data) {
      const res   = await updateRefundApi(id, data)
      const index = this.refunds.findIndex(item => item.id === id)
      if (index !== -1) this.refunds[index] = res.data.data
    },
    async deleteRefund(id) {
      await deleteRefundApi(id)
      this.refunds = this.refunds.filter(item => item.id !== id)
    },
  },
})