import { defineStore } from 'pinia'
import {
  getAllProductRecipesApi,
  getProductRecipeByIdApi,
  createProductRecipeApi,
  updateProductRecipeApi,
  deleteProductRecipeApi
} from '../api/productRecipeService'

export const useProductRecipeStore = defineStore('productRecipe', {
  state: () => ({
    productRecipes: [],
    productRecipe: null,
    pagination: {}
  }),

  actions: {
    async fetchProductRecipes(filters) {
      const res = await getAllProductRecipesApi(filters)
      this.productRecipes = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchProductRecipeById(id) {
      const res = await getProductRecipeByIdApi(id)
      this.productRecipe = res.data.data
    },
    async createProductRecipe(data) {
      const res = await createProductRecipeApi(data)
      this.productRecipes.unshift(res.data.data)
    },
    async updateProductRecipe(id, data) {
      const res = await updateProductRecipeApi(id, data)
      const index = this.productRecipes.findIndex(item => item.id === id)
      if (index !== -1) this.productRecipes[index] = res.data.data
    },
    async deleteProductRecipe(id) {
      await deleteProductRecipeApi(id)
      this.productRecipes = this.productRecipes.filter(item => item.id !== id)
    }
  }
})
