import { defineStore } from 'pinia'
import {
  getAllShiftsApi,
  getShiftByIdApi,
  createShiftApi,
  updateShiftApi,
  deleteShiftApi
} from '../api/shiftService'

export const useShiftStore = defineStore('shift', {
  state: () => ({
    shiftList: [],
    shift: null,
    pagination: {}
  }),

  actions: {
    async fetchShifts(filters) {
      const res = await getAllShiftsApi(filters)
      this.shiftList = res.data.data.data
      this.pagination = res.data
    },
    async fetchShiftById(id) {
      const res = await getShiftByIdApi(id)
      this.shift = res.data.data
    },
    async createShift(data) {
      const res = await createShiftApi(data)
      this.shiftList.unshift(res.data.data)
    },
    async updateShift(id, data) {
      const res = await updateShiftApi(id, data)
      const index = this.shiftList.findIndex(item => item.id === id)
      if (index !== -1) this.shiftList[index] = res.data.data
    },
    async deleteShift(id) {
      await deleteShiftApi(id)
      this.shiftList = this.shiftList.filter(item => item.id !== id)
    }
  }
})
