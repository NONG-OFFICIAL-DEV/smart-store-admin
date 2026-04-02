import { defineStore } from 'pinia'
import userApi from '@/api/user' // ✅ MUST be this file

export const useUserStore = defineStore('userStore', {
  state: () => ({
    users: []
  }),

  actions: {
    async fetchUsers(param) {
      const res = await userApi.getAll(param)
      this.users = res.data
    },

    async addUser(user) {
      await userApi.create(user)
    },

    async updateUser(user) {
      await userApi.update(user.id, user)
    },

    async deleteUser(id) {
      await userApi.remove(id)
      await this.fetchUsers()
    }
  }
})
