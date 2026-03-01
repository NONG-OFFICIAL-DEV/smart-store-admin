import { defineStore } from 'pinia'
import { getAllFloorPlansApi, getFloorPlanByIdApi, createFloorPlanApi, updateFloorPlanApi, deleteFloorPlanApi } from '../api/floorPlanService'

export const useFloorPlanStore = defineStore('floorPlan', {
  state: () => ({
    floorPlans:  [],
    floorPlan:   null,
    pagination:  {},
  }),

  actions: {
    async fetchFloorPlans(filters) {
      const res          = await getAllFloorPlansApi(filters)
      this.floorPlans    = res.data.data.data
      this.pagination    = res.data.data
    },
    async fetchFloorPlanById(id) {
      const res        = await getFloorPlanByIdApi(id)
      this.floorPlan   = res.data.data
    },
    async createFloorPlan(data) {
      const res = await createFloorPlanApi(data)
      this.floorPlans.unshift(res.data.data)
    },
    async updateFloorPlan(id, data) {
      const res   = await updateFloorPlanApi(id, data)
      const index = this.floorPlans.findIndex(item => item.id === id)
      if (index !== -1) this.floorPlans[index] = res.data.data
    },
    async deleteFloorPlan(id) {
      await deleteFloorPlanApi(id)
      this.floorPlans = this.floorPlans.filter(item => item.id !== id)
    },
  },
})