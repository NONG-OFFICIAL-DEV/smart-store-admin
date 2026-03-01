import http from './api'
export const shiftAssignmentService = {
  // GET /api/v1/shift-assignments
  getAll(params = {}) {
    return http.get('/v1/shift-assignments', { params })
  },

  // GET /api/v1/shift-assignments/:id
  getById(id) {
    return http.get(`/shift-assignments/${id}`)
  },

  // POST /api/v1/shift-assignments
  create(data) {
    return http.post('/v1/shift-assignments', data)
  },

  // PUT /api/v1/shift-assignments/:id
  update(id, data) {
    return http.put(`/v1/shift-assignments/${id}`, data)
  },

  // DELETE /api/v1/shift-assignments/:id
  delete(id) {
    return http.delete(`/v1/shift-assignments/${id}`)
  },

  // POST /api/v1/shift-assignments/:id/clock-in
  clockIn(id) {
    return http.post(`/v1/shift-assignments/${id}/clock-in`)
  },

  // POST /api/v1/shift-assignments/:id/clock-out
  clockOut(id) {
    return http.post(`/v1/shift-assignments/${id}/clock-out`)
  }
}
