import { defineStore } from 'pinia'
import userAPI from '@/api/user' // ✅ MUST be this file

export const useUserStore = defineStore('userStore', {
  state: () => ({
    users: []
  }),

  actions: {
    async fetchUsers(param) {
      const res = await userAPI.getAll(param)
      this.users = res.data
    },

    async addUser(user) {
      await userAPI.create(user)
    },

    async updateUser(user) {
      await userAPI.update(user.id, user)
    },

    async deleteUser(id) {
      await userAPI.remove(id)
    }
  }
})
