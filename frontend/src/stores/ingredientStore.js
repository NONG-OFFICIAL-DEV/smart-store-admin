import { defineStore } from 'pinia'
import { getAllIngredientsApi, getIngredientByIdApi, createIngredientApi, updateIngredientApi, deleteIngredientApi } from '../api/ingredientService'

export const useIngredientStore = defineStore('ingredient', {
  state: () => ({
    ingredients: [],
    ingredient:  null,
    pagination:  {},
  }),

  actions: {
    async fetchIngredients(filters) {
      const res           = await getAllIngredientsApi(filters)
      this.ingredients    = res.data.data.data
      this.pagination     = res.data.data
    },
    async fetchIngredientById(id) {
      const res          = await getIngredientByIdApi(id)
      this.ingredient    = res.data.data
    },
    async createIngredient(data) {
      const res = await createIngredientApi(data)
      this.ingredients.unshift(res.data.data)
    },
    async updateIngredient(id, data) {
      const res   = await updateIngredientApi(id, data)
      const index = this.ingredients.findIndex(item => item.id === id)
      if (index !== -1) this.ingredients[index] = res.data.data
    },
    async deleteIngredient(id) {
      await deleteIngredientApi(id)
      this.ingredients = this.ingredients.filter(item => item.id !== id)
    },
  },
})