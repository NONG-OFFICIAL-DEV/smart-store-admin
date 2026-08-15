import http from './api'

// Immutable ledger — only index/show exist on the backend, plus a nested
// byCustomer listing. There is no create/update/delete route: loyalty
// transactions are written by Customer::addPoints()/redeemPoints() only.
export const getAllLoyaltyTransactionsApi  = (filters) => http.get('/v1/loyalty-transactions', { params: filters })
export const getLoyaltyTransactionByIdApi  = (id)      => http.get(`/v1/loyalty-transactions/${id}`)
export const getLoyaltyTransactionsByCustomerApi = (customerId, filters) =>
  http.get(`/v1/customers/${customerId}/loyalty`, { params: filters })