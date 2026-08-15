import http from './api'

export const getAllKitchenDisplayTicketsApi  = (filters) => http.get('/v1/kitchen-tickets', { params: filters })
export const getKitchenDisplayTicketByIdApi  = (id)      => http.get(`/v1/kitchen-tickets/${id}`)
export const createKitchenDisplayTicketApi   = (data)    => http.post('/v1/kitchen-tickets', data)
export const updateKitchenDisplayTicketApi   = (id, data)=> http.put(`/v1/kitchen-tickets/${id}`, data)
export const startKitchenDisplayTicketApi    = (id)      => http.patch(`/v1/kitchen-tickets/${id}/start`)
export const completeKitchenDisplayTicketApi = (id)      => http.patch(`/v1/kitchen-tickets/${id}/complete`)
export const cancelKitchenDisplayTicketApi   = (id)      => http.patch(`/v1/kitchen-tickets/${id}/cancel`)
export const deleteKitchenDisplayTicketApi   = (id)      => http.delete(`/v1/kitchen-tickets/${id}`)