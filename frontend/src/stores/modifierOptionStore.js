import { defineStore } from 'pinia'
import {
  getAllModifierOptionsApi,
  getModifierOptionByIdApi,
  createModifierOptionApi,
  updateModifierOptionApi,
  deleteModifierOptionApi
} from '../api/modifierOptionService'

export const useModifierOptionStore = defineStore('modifierOption', {
  state: () => ({
    modifierOptions: [],
    modifierOption: null,
    pagination: {}
  }),

  actions: {
    // ✅ Single method — pass groupId to fetch options for one group
    async fetchModifierOptions(groupId) {
      const res = await getAllModifierOptionsApi(groupId)
      const incoming = res.data.data.data ?? res.data.data ?? []

      // Merge: replace old options for this group, keep others
      this.modifierOptions = [
        ...this.modifierOptions.filter(o => o.group_id !== groupId),
        ...incoming
      ]

      this.pagination = res.data.data ?? {}
    },

    async fetchModifierOptionById(id) {
      const res = await getModifierOptionByIdApi(id)
      this.modifierOption = res.data.data
    },

    async createModifierOption(data) {
      const res = await createModifierOptionApi(data)
      this.modifierOptions.unshift(res.data.data)
    },

    async updateModifierOption(id, data) {
      const res = await updateModifierOptionApi(id, data)
      const index = this.modifierOptions.findIndex(item => item.id === id)
      if (index !== -1) this.modifierOptions[index] = res.data.data
    },

    async deleteModifierOption(id) {
      await deleteModifierOptionApi(id)
      this.modifierOptions = this.modifierOptions.filter(item => item.id !== id)
    }
  }
})
