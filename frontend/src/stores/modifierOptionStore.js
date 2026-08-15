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
      const incoming = res.data.data ?? []

      // Merge: replace old options for this group, keep others
      this.modifierOptions = [
        ...this.modifierOptions.filter(o => o.group_id !== groupId),
        ...incoming
      ]

      this.pagination = res.data.meta ?? {}
    },

    async fetchModifierOptionById(groupId, id) {
      const res = await getModifierOptionByIdApi(groupId, id)
      this.modifierOption = res.data.data
    },

    async createModifierOption(data) {
      const res = await createModifierOptionApi(data)
      this.modifierOptions.unshift(res.data.data)
    },

    async updateModifierOption(groupId, id, data) {
      const res = await updateModifierOptionApi(groupId, id, data)
      const index = this.modifierOptions.findIndex(item => item.id === id)
      if (index !== -1) this.modifierOptions[index] = res.data.data
    },

    async deleteModifierOption(groupId, id) {
      await deleteModifierOptionApi(groupId, id)
      this.modifierOptions = this.modifierOptions.filter(item => item.id !== id)
    }
  }
})
