import http from './api'

export const getAllCategoriesApi = filters =>
  http.get('/v1/categories', { params: filters })
export const getCategoryByIdApi = id => http.get(`/v1/categories/${id}`)
export const createCategoryApi = data => http.post('/v1/categories', data)
export const updateCategoryApi = (id, data) =>
  http.put(`/v1/categories/${id}`, data)
export const deleteCategoryApi = id => http.delete(`/v1/categories/${id}`)
