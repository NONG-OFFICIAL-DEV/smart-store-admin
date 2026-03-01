import { defineStore } from 'pinia'
import { getAllCategoriesApi, getCategoryByIdApi, createCategoryApi, updateCategoryApi, deleteCategoryApi } from '../api/categoryService'

export const useCategoryStore = defineStore('category', {
  state: () => ({
    categories: [],
    category:   null,
    pagination: {},
  }),

  actions: {
    async fetchCategories(filters) {
      const res         = await getAllCategoriesApi(filters)
      this.categories   = res.data.data.data
      this.pagination   = res.data.data
    },
    async fetchCategoryById(id) {
      const res       = await getCategoryByIdApi(id)
      this.category   = res.data.data
    },
    async createCategory(data) {
      const res = await createCategoryApi(data)
      this.categories.unshift(res.data.data)
    },
    async updateCategory(id, data) {
      const res   = await updateCategoryApi(id, data)
      const index = this.categories.findIndex(item => item.id === id)
      if (index !== -1) this.categories[index] = res.data.data
    },
    async deleteCategory(id) {
      await deleteCategoryApi(id)
      this.categories = this.categories.filter(item => item.id !== id)
    },
  },
})