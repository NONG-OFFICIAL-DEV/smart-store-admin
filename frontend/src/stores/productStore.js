import { defineStore } from 'pinia'
import {
  getAllProductsApi,
  getProductByIdApi,
  createProductApi,
  updateProductApi,
  deleteProductApi,
  attachModifierGroupsApi,
  getProductForEditApi,
  updateProductApiV2
} from '../api/productService'

export const useProductStore = defineStore('product', {
  state: () => ({
    products: [],
    options: [],
    product: null,
    pagination: {}
  }),

  actions: {
    async fetchProducts(params = {}) {
      const res = await getAllProductsApi(params)
      this.products = res.data.data
      this.pagination = res.data.meta
      return res.data.data
    },
    async fetchOptions(params = {}) {
      const res = await getAllProductsApi(params)
      this.options = res.data.data ?? []
    },
    async fetchProductById(id) {
      const res = await getProductByIdApi(id)
      this.product = res.data.data
    },
    async createProduct(data) {
      const res = await createProductApi(data)
      this.products.unshift(res.data.data)
    },
    async updateProduct(id, data) {
      const res = await updateProductApi(id, data)
      return res
    },
    async deleteProduct(id) {
      await deleteProductApi(id)
      this.products = this.products.filter(item => item.id !== id)
    },
    // ✅ Attach modifier groups to a product via pivot
    async attachModifierGroups({ product_id, modifier_group_ids }) {
      await attachModifierGroupsApi(product_id, modifier_group_ids)

      // Refresh the product so modifier_groups relation is up to date
      await this.fetchProductById(product_id)
    },
    async fetchProductForEdit(id) {
      const res = await getProductForEditApi(id)
      this.product = res.data.data
      return res.data.data
    },
    async updateProductV2(id,data) {
      const res = await updateProductApiV2(id,data)
      return res
    }
  }
})
