// api/productUnitService.js
import api from './api'

const base = productId => `v1/products/${productId}/units`

export const getProductUnitsApi = productId => api.get(base(productId))
export const createProductUnitApi = (productId, data) =>
  api.post(base(productId), data)
export const updateProductUnitApi = (productId, id, data) =>
  api.put(`${base(productId)}/${id}`, data)
export const deleteProductUnitApi = (productId, id) =>
  api.delete(`${base(productId)}/${id}`)
