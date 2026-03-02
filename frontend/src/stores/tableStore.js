import { defineStore } from 'pinia'
import {
  getAllTablesApi,
  getTableByIdApi,
  createTableApi,
  updateTableApi,
  deleteTableApi
} from '../api/tableService'

export const useTableStore = defineStore('table', {
  state: () => ({
    tables: [],
    table: null,
    pagination: {}
  }),

  actions: {
    async fetchTables(filters) {
      const res = await getAllTablesApi(filters)
      this.tables = res.data.data.data
      this.pagination = res.data.data
    },
    async fetchTableById(id) {
      const res = await getTableByIdApi(id)
      this.table = res.data.data
    },
    async createTable(data) {
      const res = await createTableApi(data)
      this.tables.unshift(res.data.data)
    },
    async updateTable(id, data) {
      const res = await updateTableApi(id, data)
      const index = this.tables.findIndex(item => item.id === id)
      if (index !== -1) this.tables[index] = res.data.data
    },
    async deleteTable(id) {
      await deleteTableApi(id)
      this.tables = this.tables.filter(item => item.id !== id)
    }
  }
})
