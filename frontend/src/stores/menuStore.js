import { defineStore } from 'pinia'
import { getAllMenusApi, getMenuByIdApi, createMenuApi, updateMenuApi, deleteMenuApi } from '../api/menuService'

export const useMenuStore = defineStore('menu', {
  state: () => ({
    menus:      [],
    menu:       null,
    pagination: {},
  }),

  actions: {
    async fetchMenus(filters) {
      const res       = await getAllMenusApi(filters)
      this.menus      = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchMenuById(id) {
      const res  = await getMenuByIdApi(id)
      this.menu  = res.data.data
    },
    async createMenu(data) {
      const res = await createMenuApi(data)
      this.menus.unshift(res.data.data)
    },
    async updateMenu(id, data) {
      const res   = await updateMenuApi(id, data)
      const index = this.menus.findIndex(item => item.id === id)
      if (index !== -1) this.menus[index] = res.data.data
    },
    async deleteMenu(id) {
      await deleteMenuApi(id)
      this.menus = this.menus.filter(item => item.id !== id)
    },
  },
})