import http from './api'

export const getAllIngredientsApi   = (filters) => http.get('/v1/ingredients', { params: filters })
export const getIngredientByIdApi   = (id)      => http.get(`/v1/ingredients/${id}`)
export const createIngredientApi    = (data)    => http.post('/v1/ingredients', data)
export const updateIngredientApi    = (id, data)=> http.put(`/v1/ingredients/${id}`, data)
export const deleteIngredientApi    = (id)      => http.delete(`/v1/ingredients/${id}`)