import { defineStore } from 'pinia'
import userApi from '@/api/user' // ✅ MUST be this file

export const useUserStore = defineStore('userStore', {
  actions: {
    // Matches AppTable's fetchFn contract: { page, perPage, sortBy, sortDesc,
    // search, ...filters } in, { items, total } out.
    async fetchForTable(params) {
      const res = await userApi.getAll(params)
      return { items: res.data, total: res.meta?.total ?? 0 }
    },

    async addUser(user) {
      await userApi.create(user)
    },

    async updateUser(user) {
      await userApi.update(user.id, user)
    },

    async deleteUser(id) {
      await userApi.remove(id)
    },

    async resetPassword(id) {
      const res = await userApi.resetPassword(id)
      return res.data?.temporary_password
    }
  }
})
