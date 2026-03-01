<template>
  <!-- PAGE HEADER -->
  <div class="d-flex align-center mb-4">
    <v-btn
      size="small"
      icon="mdi-arrow-left"
      @click="$router.back()"
      variant="tonal"
      class="mr-3"
    />
    <h2 class="text-h5 font-weight-bold">Audit Log Details</h2>
  </div>

  <!-- GENERAL INFORMATION -->
  <v-card class="pa-4 mb-4 modern-card">
    <div class="d-flex align-center mb-3">
      <v-icon color="primary" class="mr-2">mdi-information-outline</v-icon>
      <h3 class="text-h6 font-weight-bold">General Info</h3>
    </div>

    <v-row dense>
      <v-col cols="12" md="6">
        <v-list density="comfortable">
          <v-list-item>
            <strong>ID:</strong> {{ store.log.id }}
          </v-list-item>

          <v-list-item>
            <strong>Action:</strong>
            <v-chip :color="chipColor" class="ms-2" size="small" variant="flat">
              {{ store.log.action_type }}
            </v-chip>
          </v-list-item>

          <v-list-item>
            <strong>Module:</strong> {{ store.log.module }}
          </v-list-item>

          <v-list-item>
            <strong>Date:</strong> {{ formatDateTime(store.log.created_at) }}
          </v-list-item>
        </v-list>
      </v-col>

      <v-col cols="12" md="6">
        <v-list density="comfortable">
          <v-list-item>
            <strong>IP Address:</strong> {{ store.log.ip_address }}
          </v-list-item>

          <v-list-item>
            <strong>Method:</strong> {{ store.log.method }}
          </v-list-item>

          <v-list-item>
            <strong>URL:</strong> {{ store.log.url }}
          </v-list-item>

          <v-list-item>
            <strong>User Agent:</strong> {{ store.log.user_agent }}
          </v-list-item>
        </v-list>
      </v-col>
    </v-row>
  </v-card>

  <!-- CHANGES / DIFF -->
  <v-card class="pa-4 mb-4 modern-card">
    <div class="d-flex align-center mb-3">
      <v-icon color="blue" class="mr-2">mdi-history</v-icon>
      <h3 class="text-h6 font-weight-bold">Changes</h3>
    </div>

    <v-table class="rounded-lg border">
      <thead>
        <tr>
          <th class="bg-grey-lighten-4 font-weight-bold">Field</th>
          <th class="bg-grey-lighten-4 font-weight-bold">Old Value</th>
          <th class="bg-grey-lighten-4 font-weight-bold">New Value</th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="(oldVal, key) in store.log.old_values"
          :key="key"
        >
          <td class="font-weight-medium text-grey-darken-3">
            {{ key }}
          </td>

          <td>
            <span
              v-if="oldVal !== store.log.new_values[key]"
              class="text-red-darken-1"
            >
              {{ oldVal }}
            </span>
            <span v-else class="text-grey">—</span>
          </td>

          <td>
            <span
              v-if="oldVal !== store.log.new_values[key]"
              class="text-green-darken-1"
            >
              {{ store.log.new_values[key] }}
            </span>
            <span v-else class="text-grey">—</span>
          </td>
        </tr>
      </tbody>
    </v-table>
  </v-card>

  <!-- RAW DATA -->
  <v-card class="pa-4 modern-card">
    <div class="d-flex align-center mb-3">
      <v-icon color="deep-purple" class="mr-2">mdi-code-json</v-icon>
      <h3 class="text-h6 font-weight-bold">Raw JSON Data</h3>
    </div>

    <v-expansion-panels variant="accordion">
      <v-expansion-panel title="Old Values (JSON)">
        <v-expansion-panel-text>
          <pre class="json-box">{{ store.log.old_values }}</pre>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel title="New Values (JSON)">
        <v-expansion-panel-text>
          <pre class="json-box">{{ store.log.new_values }}</pre>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </v-card>
</template>


<script setup>
  import { onMounted, computed } from 'vue'
  import { useRoute } from 'vue-router'
  import { useDate } from '@/composables/useDate'
  import { useAuditLogStore } from '@/stores/auditLogStore'

  const { formatDateTime } = useDate()
  const store = useAuditLogStore()

  const route = useRoute()
  const id = route.params.id

  const chipColor = computed(() => {
    switch (store.log.action_type) {
      case 'create':
        return 'green'
      case 'update':
        return 'blue'
      case 'delete':
        return 'red'
      default:
        return 'grey'
    }
  })

  const fetchLog = async () => {
    store.getById(id)
  }

  onMounted(fetchLog)
</script>

<style scoped>
.modern-card {
  border-radius: 14px;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08) !important;
}

.json-box {
  background: #f5f5f5;
  padding: 12px;
  border-radius: 8px;
  font-size: 0.85rem;
  white-space: pre-wrap;
  border: 1px solid #e0e0e0;
}
</style>

