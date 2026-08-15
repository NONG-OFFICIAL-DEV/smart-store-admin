import { defineStore } from 'pinia'
import { getAllBranchHoursApi, getBranchHourByIdApi, createBranchHourApi, updateBranchHourApi, deleteBranchHourApi } from '../api/branchHourService'

export const useBranchHourStore = defineStore('branchHour', {
  state: () => ({
    branchHours: [],
    branchHour:  null,
    pagination:  {},
  }),

  actions: {
    async fetchBranchHours(filters) {
      const res          = await getAllBranchHoursApi(filters)
      this.branchHours   = res.data.data.data
      this.pagination    = res.data.data
    },
    async fetchBranchHourById(id) {
      const res       = await getBranchHourByIdApi(id)
      this.branchHour = res.data.data
    },
    async createBranchHour(data) {
      const res = await createBranchHourApi(data)
      this.branchHours.unshift(res.data.data)
    },
    async updateBranchHour(id, data) {
      const res   = await updateBranchHourApi(id, data)
      const index = this.branchHours.findIndex(item => item.id === id)
      if (index !== -1) this.branchHours[index] = res.data.data
    },
    async deleteBranchHour(id) {
      await deleteBranchHourApi(id)
      this.branchHours = this.branchHours.filter(item => item.id !== id)
    },
  },
})