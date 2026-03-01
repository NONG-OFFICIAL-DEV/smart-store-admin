<template>
  <v-container fluid class="pa-0">
    <!-- Header -->
    <custom-title
      title="Branches"
      subtitle="Manage your branches"
      icon="mdi-map-marker-path"
    >
      <template #right>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          elevation="0"
          @click="openCreate"
        >
          Add Branch
        </v-btn>
      </template>
    </custom-title>

    <!-- Branches Table -->
    <v-card rounded="lg" elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="branchStore.branches.data"
        item-value="id"
      >
        <template #item.is_open="{ item }">
          <v-chip
            :color="item.is_open ? 'success' : 'error'"
            size="small"
            variant="tonal"
          >
            {{ item.is_open ? 'Open' : 'Closed' }}
          </v-chip>
        </template>

        <template #item.is_active="{ item }">
          <v-chip
            :color="item.is_active ? 'success' : 'error'"
            size="small"
            variant="tonal"
          >
            {{ item.is_active ? 'Active' : 'Inactive' }}
          </v-chip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <v-btn
            icon="mdi-pencil-outline"
            variant="text"
            size="small"
            @click="openEdit(item)"
          />
          <v-btn
            icon="mdi-delete-outline"
            variant="text"
            size="small"
            color="error"
            @click="confirmDelete(item)"
          />
        </template>
      </v-data-table>
    </v-card>
    <!-- Branch Dialog -->
    <BranchDialog
      v-model="dialog.show"
      :branch="dialog.branch"
      @saved="handleSave"
    />
  </v-container>
</template>

<script setup>
  import { ref, reactive, onMounted } from 'vue'
  import { useBranchStore } from '@/stores/branchStore'
  import BranchDialog from '@/components/branches/BranchDialog.vue'

  const branchStore = useBranchStore()

  const headers = [
    { title: 'Name', key: 'name' },
    { title: 'Tenant', key: 'tenant.name' },
    { title: 'Type', key: 'type' },
    { title: 'City', key: 'city' },
    { title: 'Open', key: 'is_open' },
    { title: 'Status', key: 'is_active' },
    { title: 'Actions', key: 'actions', sortable: false }
  ]

  const dialog = reactive({
    show: false,
    branch: null
  })

  const fetchBranches = async () => {
    await branchStore.fetchBranches()
  }

  const openCreate = () => {
    dialog.branch = null
    dialog.show = true
  }

  const openEdit = branch => {
    dialog.branch = { ...branch }
    dialog.show = true
  }

  const handleSave = async branchData => {
    if (branchData.id) {
      await branchStore.updateBranch(branchData.id, branchData)
    } else {
      await branchStore.createBranch(branchData)
    }
    await branchStore.fetchBranches()
  }

  const confirmDelete = branch => {
    if (confirm(`Are you sure you want to delete branch "${branch.name}"?`)) {
      branchStore.deleteBranch(branch.id)
    }
  }

  onMounted(fetchBranches)
</script>
