import http from './api'

export const getAllBranchesApi = filters =>
  http.get('/v1/branches', { params: filters })
export const getBranchByIdApi = id => http.get(`/v1/branches/${id}`)
export const createBranchApi = data => http.post('/v1/branches', data)
// PHP/Laravel can't parse a multipart PUT body — same _method spoof as
// updateTenantProfileApi's logo upload: POST with ?_method=PUT when a
// file (customer display promo/QR image) is attached.
export const updateBranchApi = (id, data) => {
  if (data instanceof FormData) {
    return http.post(`/v1/branches/${id}?_method=PUT`, data, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  }
  return http.put(`/v1/branches/${id}`, data)
}
export const deleteBranchApi = id => http.delete(`/v1/branches/${id}`)
export const toggleOpen = id => http.post(`v1/branches/${id}/toggle-open`)
