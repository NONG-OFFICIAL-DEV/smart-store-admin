import http from './api'

export const getAllIngredientsApi   = (filters) => http.get('/ingredients', { params: filters })
export const getIngredientByIdApi   = (id)      => http.get(`/ingredients/${id}`)
export const createIngredientApi    = (data)    => http.post('/ingredients', data)
export const updateIngredientApi    = (id, data)=> http.put(`/ingredients/${id}`, data)
export const deleteIngredientApi    = (id)      => http.delete(`/ingredients/${id}`)