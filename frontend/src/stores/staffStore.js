import { defineStore } from 'pinia'
import {
  getAllStaffApi,
  getStaffByIdApi,
  createStaffApi,
  updateStaffApi,
  deleteStaffApi
} from '../api/staffService'

export const useStaffStore = defineStore('staff', {
  state: () => ({
    staffList: [],
    staff: null,
    pagination: {}
  }),

  actions: {
    async fetchStaff(filters) {
      const res = await getAllStaffApi(filters)
      this.staffList = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchStaffById(id) {
      const res = await getStaffByIdApi(id)
      this.staff = res.data.data
    },
    async createStaff(data) {
      const res = await createStaffApi(data)
      this.staffList.unshift(res.data.data)
    },
    async updateStaff(id, data) {
      const res = await updateStaffApi(id, data)
      const index = this.staffList.findIndex(item => item.id === id)
      if (index !== -1) this.staffList[index] = res.data.data
    },
    async deleteStaff(id) {
      await deleteStaffApi(id)
      this.staffList = this.staffList.filter(item => item.id !== id)
    }
  }
})
