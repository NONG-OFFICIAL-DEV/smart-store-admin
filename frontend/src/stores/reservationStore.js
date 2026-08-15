import { defineStore } from 'pinia'
import { getAllReservationsApi, getReservationByIdApi, createReservationApi, updateReservationApi, deleteReservationApi } from '../api/reservationService'

export const useReservationStore = defineStore('reservation', {
  state: () => ({
    reservations: [],
    reservation:  null,
    pagination:   {},
    loading:      false,
  }),

  actions: {
    async fetchReservations(filters) {
      this.loading = true
      try {
        const res          = await getAllReservationsApi(filters)
        this.reservations  = res.data.data
        this.pagination    = res.data.meta
      } finally {
        this.loading = false
      }
    },
    async loadMoreReservations(filters) {
      const res         = await getAllReservationsApi(filters)
      this.reservations.push(...res.data.data)
      this.pagination   = res.data.meta
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