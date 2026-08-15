import { defineStore } from 'pinia'
import { getAllKitchenDisplayTicketsApi, getKitchenDisplayTicketByIdApi, createKitchenDisplayTicketApi, updateKitchenDisplayTicketApi, startKitchenDisplayTicketApi, completeKitchenDisplayTicketApi, cancelKitchenDisplayTicketApi, deleteKitchenDisplayTicketApi } from '../api/kitchenDisplayTicketService'

export const useKitchenDisplayTicketStore = defineStore('kitchenDisplayTicket', {
  state: () => ({
    kitchenDisplayTickets: [],
    kitchenDisplayTicket:  null,
    pagination:            {},
  }),

  actions: {
    async fetchKitchenDisplayTickets(filters) {
      const res                    = await getAllKitchenDisplayTicketsApi(filters)
      this.kitchenDisplayTickets   = res.data.data
      this.pagination              = res.data.meta
    },
    async fetchKitchenDisplayTicketById(id) {
      const res                   = await getKitchenDisplayTicketByIdApi(id)
      this.kitchenDisplayTicket   = res.data.data
    },
    async createKitchenDisplayTicket(data) {
      const res = await createKitchenDisplayTicketApi(data)
      this.kitchenDisplayTickets.unshift(res.data.data)
    },
    async updateKitchenDisplayTicket(id, data) {
      const res   = await updateKitchenDisplayTicketApi(id, data)
      const index = this.kitchenDisplayTickets.findIndex(item => item.id === id)
      if (index !== -1) this.kitchenDisplayTickets[index] = res.data.data
    },
    async startKitchenDisplayTicket(id) {
      const res   = await startKitchenDisplayTicketApi(id)
      const index = this.kitchenDisplayTickets.findIndex(item => item.id === id)
      if (index !== -1) this.kitchenDisplayTickets[index] = res.data.data
    },
    async completeKitchenDisplayTicket(id) {
      const res   = await completeKitchenDisplayTicketApi(id)
      const index = this.kitchenDisplayTickets.findIndex(item => item.id === id)
      if (index !== -1) this.kitchenDisplayTickets[index] = res.data.data
    },
    async cancelKitchenDisplayTicket(id) {
      const res   = await cancelKitchenDisplayTicketApi(id)
      const index = this.kitchenDisplayTickets.findIndex(item => item.id === id)
      if (index !== -1) this.kitchenDisplayTickets[index] = res.data.data
    },
    async deleteKitchenDisplayTicket(id) {
      await deleteKitchenDisplayTicketApi(id)
      this.kitchenDisplayTickets = this.kitchenDisplayTickets.filter(item => item.id !== id)
    },
  },
})