import http from './api' // <-- your axios instance (with baseURL set)

/**
 * Category http service
 */
export default {
  // Get all categories
  getAll(filters) {
    return http.get('/audit-logs', { params: filters })
  },

  getById(id) {
    return http.get(`/audit-logs/${id}`)
  },

  export() {}
}
