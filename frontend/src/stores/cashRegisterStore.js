import { defineStore } from 'pinia'
import {
  getAllCashRegistersApi,
  getCashRegisterByIdApi,
  createCashRegisterApi,
  updateCashRegisterApi,
  deleteCashRegisterApi
} from '@/api/cashRegisterService'

export const useCashRegisterStore = defineStore('cashRegister', {
  state: () => ({
    cashRegisters: { data: [], total: 0 },
    cashRegister: null,
    loading: false,
    error: null
  }),

  actions: {
    async fetchCashRegisters(filters = {}) {
      this.loading = true
      try {
        const res = await getAllCashRegistersApi(filters)
        this.cashRegisters = { ...res.data.meta, data: res.data.data }
      } catch (e) {
        this.error = e?.response?.data?.message ?? 'Failed to load cash registers'
      } finally {
        this.loading = false
      }
    },

    async fetchCashRegisterById(id) {
      const res = await getCashRegisterByIdApi(id)
      this.cashRegister = res.data.data
      return res.data.data
    },

    async addCashRegister(data) {
      const res = await createCashRegisterApi(data)
      this.cashRegisters.data?.unshift(res.data.data)
      this.cashRegisters.total = (this.cashRegisters.total ?? 0) + 1
      return res.data.data
    },

    async updateCashRegister(register) {
      const res = await updateCashRegisterApi(register.id, register)
      const index = this.cashRegisters.data?.findIndex(r => r.id === register.id)
      if (index !== -1) this.cashRegisters.data[index] = res.data.data
      return res.data.data
    },

    async removeCashRegister(id) {
      await deleteCashRegisterApi(id)
      this.cashRegisters.data = this.cashRegisters.data?.filter(r => r.id !== id)
      this.cashRegisters.total = Math.max(0, (this.cashRegisters.total ?? 1) - 1)
    }
  }
})
