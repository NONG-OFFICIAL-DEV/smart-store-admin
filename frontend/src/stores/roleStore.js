import { defineStore } from 'pinia'
import {
  getAllRolesApi,
  getRoleByIdApi,
  createRoleApi,
  updateRoleApi,
  deleteRoleApi
} from '../api/roleService'

export const useRoleStore = defineStore('role', {
  state: () => ({
    roles: [],
    role: null,
    pagination: {}
  }),

  actions: {
    async fetchRoles(filters) {
      const res = await getAllRolesApi(filters)
      this.roles = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchRoleById(id) {
      const res = await getRoleByIdApi(id)
      this.role = res.data.data
    },
    async createRole(data) {
      const res = await createRoleApi(data)
      this.roles.unshift(res.data.data)
    },
    async updateRole(id, data) {
      const res = await updateRoleApi(id, data)
      const index = this.roles.findIndex(item => item.id === id)
      if (index !== -1) this.roles[index] = res.data.data
    },
    async deleteRole(id) {
      await deleteRoleApi(id)
      this.roles = this.roles.filter(item => item.id !== id)
    }
  }
})
