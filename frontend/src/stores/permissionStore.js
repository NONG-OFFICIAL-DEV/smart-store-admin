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
      // Permission catalog is small/admin-managed and the page (grouping,
      // stats, client-side filtering) assumes the full set is in memory —
      // default to fetching everything unless a caller overrides perPage.
      const res = await getAllPermissionsApi({ perPage: -1, ...filters })
      this.permissions = res.data.data
      this.pagination = res.data.meta
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
