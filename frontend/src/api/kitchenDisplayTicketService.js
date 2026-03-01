import http from './api'

export const getAllKitchenDisplayTicketsApi  = (filters) => http.get('/kitchen-display-tickets', { params: filters })
export const getKitchenDisplayTicketByIdApi  = (id)      => http.get(`/kitchen-display-tickets/${id}`)
export const createKitchenDisplayTicketApi   = (data)    => http.post('/kitchen-display-tickets', data)
export const updateKitchenDisplayTicketApi   = (id, data)=> http.put(`/kitchen-display-tickets/${id}`, data)
export const deleteKitchenDisplayTicketApi   = (id)      => http.delete(`/kitchen-display-tickets/${id}`)