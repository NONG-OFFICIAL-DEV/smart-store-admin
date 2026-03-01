import http from './api'

export const getAllLoyaltyTransactionsApi  = (filters) => http.get('/loyalty-transactions', { params: filters })
export const getLoyaltyTransactionByIdApi  = (id)      => http.get(`/loyalty-transactions/${id}`)
export const createLoyaltyTransactionApi   = (data)    => http.post('/loyalty-transactions', data)
export const updateLoyaltyTransactionApi   = (id, data)=> http.put(`/loyalty-transactions/${id}`, data)
export const deleteLoyaltyTransactionApi   = (id)      => http.delete(`/loyalty-transactions/${id}`)