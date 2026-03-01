import { defineStore } from 'pinia'
import { ref } from 'vue'
import { shiftAssignmentService } from '@/api/shiftAssignmentService'

export const useShiftAssignmentStore = defineStore('shiftAssignment', () => {
  const assignmentList = ref([])
  const pagination = ref({})
  const loading = ref(false)
  const error = ref(null)

  // ── Fetch all assignments ───────────────────────────────────────────────────
  async function fetchAssignments(params = {}) {
    loading.value = true
    error.value = null
    try {
      const res = await shiftAssignmentService.getAll(params)
      assignmentList.value = res.data.data || []
      pagination.value = res.data.pagination || {}
    } catch (e) {
      error.value = e?.response?.data?.message || 'Failed to fetch assignments'
      throw e
    } finally {
      loading.value = false
    }
  }

  // ── Create assignment ───────────────────────────────────────────────────────
  async function createAssignment(data) {
    const res = await shiftAssignmentService.create(data)
    await fetchAssignments()
    return res.data
  }

  // ── Update assignment ───────────────────────────────────────────────────────
  async function updateAssignment(id, data) {
    const res = await shiftAssignmentService.update(id, data)
    await fetchAssignments()
    return res.data
  }

  // ── Delete assignment ───────────────────────────────────────────────────────
  async function deleteAssignment(id) {
    const res = await shiftAssignmentService.delete(id)
    await fetchAssignments()
    return res.data
  }

  // ── Clock in ────────────────────────────────────────────────────────────────
  async function clockIn(id) {
    const res = await shiftAssignmentService.clockIn(id)
    // Update only the affected item in the list (no full refetch needed)
    const idx = assignmentList.value.findIndex(a => a.id === id)
    if (idx !== -1) assignmentList.value[idx] = res.data.data
    return res.data
  }

  // ── Clock out ───────────────────────────────────────────────────────────────
  async function clockOut(id) {
    const res = await shiftAssignmentService.clockOut(id)
    const idx = assignmentList.value.findIndex(a => a.id === id)
    if (idx !== -1) assignmentList.value[idx] = res.data.data
    return res.data
  }

  return {
    assignmentList,
    pagination,
    loading,
    error,
    fetchAssignments,
    createAssignment,
    updateAssignment,
    deleteAssignment,
    clockIn,
    clockOut
  }
})
