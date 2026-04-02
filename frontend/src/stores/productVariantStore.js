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
      return res
    },
    async updateProductVariant(id, data) {
      const res = await updateProductVariantApi(id, data)
      return res
    },
    async deleteProductVariant(id) {
      await deleteProductVariantApi(id)
      await this.fetchProductVariants()
    }
  }
})
