import http from './api'

export const getAllMenusApi = filters =>
  http.get('/v1/menus', { params: filters })
export const getMenuByIdApi = id => http.get(`/v1/menus/${id}`)
export const createMenuApi = data => http.post('/v1/menus', data)
export const updateMenuApi = (id, data) => http.put(`/v1/menus/${id}`, data)
export const deleteMenuApi = id => http.delete(`/v1/menus/${id}`)
