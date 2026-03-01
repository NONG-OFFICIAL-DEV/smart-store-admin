import { defineStore } from 'pinia'
import {
  getAllProductVariantsApi,
  getProductVariantByIdApi,
  createProductVariantApi,
  updateProductVariantApi,
  deleteProductVariantApi
} from '../api/productVariantService'

export const useProductVariantStore = defineStore('productVariant', {
  state: () => ({
    productVariants: [],
    productVariant: null,
    pagination: {}
  }),

  actions: {
    async fetchProductVariants(filters) {
      const res = await getAllProductVariantsApi(filters)
      this.productVariants = res.data.data
      this.pagination = res.data
    },
    async fetchProductVariantById(id) {
      const res = await getProductVariantByIdApi(id)
      this.productVariant = res.data.data
    },
    async createProductVariant(data) {
      const res = await createProductVariantApi(data)
      this.productVariants.unshift(res.data.data)
    },
    async updateProductVariant(id, data) {
      const res = await updateProductVariantApi(id, data)
      const index = this.productVariants.findIndex(item => item.id === id)
      if (index !== -1) this.productVariants[index] = res.data.data
    },
    async deleteProductVariant(id) {
      await deleteProductVariantApi(id)
      this.productVariants = this.productVariants.filter(item => item.id !== id)
    }
  }
})
