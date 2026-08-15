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

  getters: {
    // Roles that may be picked in a role-assignment dropdown — excludes the
    // protected, backend-provisioned Owner role (never assignable via UI).
    assignableRoles: state => state.roles.filter(r => r.code !== 'owner')
  },

  actions: {
    async fetchRoles(filters) {
      const res = await getAllRolesApi(filters)
      this.roles = res.data.data
      this.pagination = res.data.meta
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
