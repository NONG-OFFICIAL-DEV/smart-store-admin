import http from './api'

// Read-only — cash in/out are created via cashDrawerService's cashInApi/
// cashOutApi (which enforce shift-open/balance checks server-side), never
// posted here directly. Append-only audit log, no update/delete.
export const getAllCashTransactionsApi = (filters) => http.get('/v1/cash-transactions', { params: filters })
