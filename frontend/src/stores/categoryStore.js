import { defineStore } from 'pinia'
import {
  getAllCategoriesApi,
  getCategoryByIdApi,
  createCategoryApi,
  updateCategoryApi,
  deleteCategoryApi,
  reorderCategoriesApi,
  deleteCategoryWithReassignmentApi
} from '../api/categoryService'

export const useCategoryStore = defineStore('category', {
  state: () => ({
    categories: [],
    category: null,
    pagination: {},
    // CategoryView.vue already binds :loading="categoryStore.loading", but
    // this state never existed — the loading spinner was always a no-op.
    loading: false
  }),

  actions: {
    async fetchCategories(filters) {
      this.loading = true
      try {
        const res = await getAllCategoriesApi(filters)
        this.categories = res.data.data
        this.pagination = res.data.meta
        return { items: res.data.data, total: res.data.meta.total }
      } finally {
        this.loading = false
      }
    },
    async fetchCategoryById(id) {
      const res = await getCategoryByIdApi(id)
      this.category = res.data.data
    },
    async createCategory(data) {
      const res = await createCategoryApi(data)
      return res
    },
    async updateCategory(id, data) {
      const res = await updateCategoryApi(id, data)
      return res
    },
    async deleteCategory(id) {
      await deleteCategoryApi(id)
      this.categories = this.categories.filter(item => item.id !== id)
    },
    async reorderCategories(orderedIds) {
      await reorderCategoriesApi(orderedIds)
    },
    async deleteCategoryWithReassignment(id, reassignTo) {
      await deleteCategoryWithReassignmentApi(id, reassignTo)
      this.categories = this.categories.filter(item => item.id !== id)
    }
  }
})
