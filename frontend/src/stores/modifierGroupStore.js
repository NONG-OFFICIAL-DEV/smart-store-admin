import { defineStore } from 'pinia'
import {
  getAllModifierGroupsApi,
  getModifierGroupByIdApi,
  createModifierGroupApi,
  updateModifierGroupApi,
  deleteModifierGroupApi
} from '../api/modifierGroupService'

export const useModifierGroupStore = defineStore('modifierGroup', {
  state: () => ({
    modifierGroups: [],
    modifierGroup: null,
    pagination: {}
  }),

  actions: {
    async fetchModifierGroups(filters) {
      const res = await getAllModifierGroupsApi(filters)
      this.modifierGroups = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchModifierGroupById(id) {
      const res = await getModifierGroupByIdApi(id)
      this.modifierGroup = res.data.data
    },
    async createModifierGroup(data) {
      const res = await createModifierGroupApi(data)
      this.modifierGroups.unshift(res.data.data)
    },
    async updateModifierGroup(id, data) {
      const res = await updateModifierGroupApi(id, data)
      const index = this.modifierGroups.findIndex(item => item.id === id)
      if (index !== -1) this.modifierGroups[index] = res.data.data
    },
    async deleteModifierGroup(id) {
      await deleteModifierGroupApi(id)
      this.modifierGroups = this.modifierGroups.filter(item => item.id !== id)
    }
  }
})
