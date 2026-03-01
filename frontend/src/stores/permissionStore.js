import { defineStore } from 'pinia'
import {
  getAllPermissionsApi,
  getPermissionByIdApi,
  createPermissionApi,
  updatePermissionApi,
  deletePermissionApi
} from '../api/permissionService'

export const usePermissionStore = defineStore('permission', {
  state: () => ({
    permissions: [],
    permission: null,
    pagination: {}
  }),

  actions: {
    async fetchPermissions(filters) {
      const res = await getAllPermissionsApi(filters)
      this.permissions = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchPermissionById(id) {
      const res = await getPermissionByIdApi(id)
      this.permission = res.data.data
    },
    async createPermission(data) {
      const res = await createPermissionApi(data)
      this.permissions.unshift(res.data.data)
    },
    async updatePermission(id, data) {
      const res = await updatePermissionApi(id, data)
      const index = this.permissions.findIndex(item => item.id === id)
      if (index !== -1) this.permissions[index] = res.data.data
    },
    async deletePermission(id) {
      await deletePermissionApi(id)
      this.permissions = this.permissions.filter(item => item.id !== id)
    }
  }
})
