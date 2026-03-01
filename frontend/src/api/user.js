import http from './api'

const API_URL = '/users'

export default {
  async getAll(param) {
    const res = await http.get(API_URL, { params: param })
    return res.data 
  },

  async getById(id) {
    const res = await http.get(`${API_URL}/${id}`)
    return res.data
  },

  async create(user) {
    const res = await http.post(API_URL, user)
    return res.data
  },

  async update(id, user) {
    const res = await http.put(`${API_URL}/${id}`, user)
    return res.data
  },

  async remove(id) {
    await http.delete(`${API_URL}/${id}`)
    return true
  }
}
