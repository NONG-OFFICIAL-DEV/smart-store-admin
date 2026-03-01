import { defineStore } from 'pinia'
import {
  getAllProductsApi,
  getProductByIdApi,
  createProductApi,
  updateProductApi,
  deleteProductApi
} from '../api/productService'

export const useProductStore = defineStore('product', {
  state: () => ({
    products: [],
    product: null,
    pagination: {}
  }),

  actions: {
    async fetchProducts(filters) {
      const res = await getAllProductsApi(filters)
      this.products = res.data.data
      this.pagination = res.data
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
      const index = this.products.findIndex(item => item.id === id)
      if (index !== -1) this.products[index] = res.data.data
    },
    async deleteProduct(id) {
      await deleteProductApi(id)
      this.products = this.products.filter(item => item.id !== id)
    }
  }
})
