import { defineStore } from 'pinia'
import { getAllReservationsApi, getReservationByIdApi, createReservationApi, updateReservationApi, deleteReservationApi } from '../api/reservationService'

export const useReservationStore = defineStore('reservation', {
  state: () => ({
    reservations: [],
    reservation:  null,
    pagination:   {},
  }),

  actions: {
    async fetchReservations(filters) {
      const res           = await getAllReservationsApi(filters)
      this.reservations   = res.data.data.data
      this.pagination     = res.data.data
    },
    async fetchReservationById(id) {
      const res          = await getReservationByIdApi(id)
      this.reservation   = res.data.data
    },
    async createReservation(data) {
      const res = await createReservationApi(data)
      this.reservations.unshift(res.data.data)
    },
    async updateReservation(id, data) {
      const res   = await updateReservationApi(id, data)
      const index = this.reservations.findIndex(item => item.id === id)
      if (index !== -1) this.reservations[index] = res.data.data
    },
    async deleteReservation(id) {
      await deleteReservationApi(id)
      this.reservations = this.reservations.filter(item => item.id !== id)
    },
  },
})