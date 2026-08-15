<template>
  <v-container fluid class="pa-0">
    <AppPageHeader
      :title="log.description ?? $t('audit_log.detail_fallback')"
      show-back
      :breadcrumbs="[
        { title: $t('audit_log.title'), to: '/audit-logs' },
        { title: log.description ?? $t('audit_log.detail_fallback') }
      ]"
    ></AppPageHeader>

    <v-row dense>
      <!-- ── Left Column ───────────────────────────────────────────── -->
      <v-col cols="12" md="7">
        <!-- General Info -->
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-3 d-flex align-center gap-2">
            <v-icon icon="mdi-information-outline" color="primary" size="20" />
            <span class="text-body-1 font-weight-bold">
              {{ $t('audit_log.general_info') }}
            </span>
          </v-card-title>
          <v-divider />
          <v-card-text class="pa-5">
            <div class="info-grid">
              <div class="info-row">
                <span class="info-label">{{ $t('audit_log.action') }}</span>
                <v-chip
                  :color="actionColor(log.action)"
                  size="small"
                  variant="tonal"
                  label
                >
                  {{ log.action }}
                </v-chip>
              </div>

              <div class="info-row">
                <span class="info-label">{{ $t('form.description') }}</span>
                <span class="info-value">{{ log.description || '—' }}</span>
              </div>

              <div class="info-row">
                <span class="info-label">{{ $t('audit_log.entity') }}</span>
                <span class="info-value" v-if="log.entity_type">
                  {{ log.entity_type }}
                  <span class="text-caption text-grey ml-1">
                    #{{ log.entity_id?.slice(0, 8) }}...
                  </span>
                </span>
                <span v-else class="text-grey">—</span>
              </div>

              <div class="info-row">
                <span class="info-label">{{ $t('form.date') }}</span>
                <span class="info-value">
                  {{ formatDateTime(log.created_at) }}
                </span>
              </div>

              <div class="info-row">
                <span class="info-label">
                  {{ $t('audit_log.ip_address') }}
                </span>
                <code class="text-caption bg-grey-lighten-4 px-2 py-1 rounded">
                  {{ log.ip_address || '—' }}
                </code>
              </div>

              <div class="info-row">
                <span class="info-label">
                  {{ $t('audit_log.user_agent') }}
                </span>
                <span
                  class="info-value text-caption text-grey"
                  style="word-break: break-all"
                >
                  {{ log.user_agent || '—' }}
                </span>
              </div>
            </div>
          </v-card-text>
        </v-card>

        <!-- Changes / Diff -->
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-3 d-flex align-center gap-2">
            <v-icon icon="mdi-swap-horizontal" color="blue" size="20" />
            <span class="text-body-1 font-weight-bold">
              {{ $t('audit_log.changes') }}
            </span>
          </v-card-title>
          <v-divider />
          <v-card-text class="pa-0">
            <!-- Has before/after payload -->
            <template v-if="hasDiff">
              <v-table density="compact">
                <thead>
                  <tr>
                    <th class="text-left py-3 px-5">
                      {{ $t('audit_log.field') }}
                    </th>
                    <th class="text-left py-3 px-5 text-red">
                      {{ $t('audit_log.before') }}
                    </th>
                    <th class="text-left py-3 px-5 text-green">
                      {{ $t('audit_log.after') }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(val, key) in diffFields" :key="key">
                    <td class="py-2 px-5 font-weight-medium text-caption">
                      {{ key }}
                    </td>
                    <td class="py-2 px-5">
                      <span class="text-caption text-red-darken-1">
                        {{ formatVal(log.payload?.before?.[key]) }}
                      </span>
                    </td>
                    <td class="py-2 px-5">
                      <span class="text-caption text-green-darken-1">
                        {{ formatVal(val) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </v-table>
            </template>

            <!-- Only after (create) -->
            <template v-else-if="log.payload?.after">
              <v-table density="compact">
                <thead>
                  <tr>
                    <th class="text-left py-3 px-5">
                      {{ $t('audit_log.field') }}
                    </th>
                    <th class="text-left py-3 px-5 text-green">
                      {{ $t('audit_log.value') }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(val, key) in log.payload.after" :key="key">
                    <td class="py-2 px-5 font-weight-medium text-caption">
                      {{ key }}
                    </td>
                    <td class="py-2 px-5 text-caption text-green-darken-1">
                      {{ formatVal(val) }}
                    </td>
                  </tr>
                </tbody>
              </v-table>
            </template>

            <!-- No payload -->
            <div v-else class="pa-5 text-caption text-grey text-center">
              {{ $t('audit_log.no_change_data') }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- ── Right Column ──────────────────────────────────────────── -->
      <v-col cols="12" md="5">
        <!-- User Info -->
        <v-card rounded="xl" elevation="0" border class="mb-4">
          <v-card-title class="pa-5 pb-3 d-flex align-center gap-2">
            <v-icon icon="mdi-account-outline" color="secondary" size="20" />
            <span class="text-body-1 font-weight-bold">
              {{ $t('audit_log.performed_by') }}
            </span>
          </v-card-title>
          <v-divider />
          <v-card-text class="pa-5">
            <div class="d-flex align-center gap-3 mb-4">
              <v-avatar color="primary" variant="tonal" size="44" rounded="lg">
                <span class="text-body-2 font-weight-bold">
                  {{ initials }}
                </span>
              </v-avatar>
              <div>
                <div class="text-body-2 font-weight-bold">
                  {{ log.user_name || '—' }}
                </div>
                <div class="text-caption text-grey">{{ log.user_email }}</div>
              </div>
            </div>
            <div class="info-grid">
              <div class="info-row">
                <span class="info-label">{{ $t('audit_log.user_id') }}</span>
                <code class="text-caption text-grey">
                  {{ log.user_id?.slice(0, 16) }}...
                </code>
              </div>
              <div class="info-row">
                <span class="info-label">{{ $t('form.branch') }}</span>
                <span class="info-value">
                  {{ log.branch_id?.slice(0, 8) || $t('audit_log.all_branches') }}
                </span>
              </div>
              <div class="info-row">
                <span class="info-label">{{ $t('audit_log.tenant') }}</span>
                <span class="info-value">
                  {{ log.tenant_id?.slice(0, 8) || $t('audit_log.super_admin') }}
                </span>
              </div>
            </div>
          </v-card-text>
        </v-card>

        <!-- Raw JSON -->
        <v-card rounded="xl" elevation="0" border>
          <v-card-title class="pa-5 pb-3 d-flex align-center gap-2">
            <v-icon icon="mdi-code-json" color="deep-purple" size="20" />
            <span class="text-body-1 font-weight-bold">
              {{ $t('audit_log.raw_payload') }}
            </span>
          </v-card-title>
          <v-divider />
          <v-card-text class="pa-4">
            <v-expansion-panels variant="accordion" elevation="0">
              <v-expansion-panel
                v-if="log.payload?.before"
                rounded="lg"
                :title="$t('audit_log.before')"
              >
                <v-expansion-panel-text>
                  <pre class="json-box">{{
                    JSON.stringify(log.payload.before, null, 2)
                  }}</pre>
                </v-expansion-panel-text>
              </v-expansion-panel>
              <v-expansion-panel
                v-if="log.payload?.after"
                rounded="lg"
                :title="$t('audit_log.after')"
              >
                <v-expansion-panel-text>
                  <pre class="json-box">{{
                    JSON.stringify(log.payload.after, null, 2)
                  }}</pre>
                </v-expansion-panel-text>
              </v-expansion-panel>
              <div
                v-if="!log.payload"
                class="text-caption text-grey text-center py-3"
              >
                {{ $t('audit_log.no_payload_data') }}
              </div>
            </v-expansion-panels>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
  import { onMounted, computed } from 'vue'
  import { useRoute } from 'vue-router'
  import { useDate } from '@/composables/useDate'
  import { useAuditLogStore } from '@/stores/auditLogStore'
  import AppPageHeader from '@/components/customs/AppPageHeader.vue'
  import { useAvatar } from '@/composables/useAvatar'

  const { formatDateTime } = useDate()
  const { getInitials } = useAvatar()
  const store = useAuditLogStore()
  const route = useRoute()

  const log = computed(() => store.log)

  // ── Initials ───────────────────────────────────────────────────────────────────
  const initials = computed(() => getInitials(log.value?.user_name, ''))

  // ── Action color ───────────────────────────────────────────────────────────────
  const actionColor = action => {
    if (action?.includes('login')) return 'success'
    if (action?.includes('logout')) return 'grey'
    if (action?.includes('created')) return 'primary'
    if (action?.includes('updated')) return 'warning'
    if (action?.includes('deleted')) return 'error'
    return 'secondary'
  }

  // ── Diff — only show fields that changed ──────────────────────────────────────
  const hasDiff = computed(
    () => log.value?.payload?.before && log.value?.payload?.after
  )

  const diffFields = computed(() => {
    if (!hasDiff.value) return {}
    const after = log.value.payload.after
    const before = log.value.payload.before
    // Only return fields that actually changed
    return Object.fromEntries(
      Object.entries(after).filter(
        ([k, v]) => JSON.stringify(v) !== JSON.stringify(before[k])
      )
    )
  })

  // ── Format value for display ───────────────────────────────────────────────────
  const formatVal = val => {
    if (val === null || val === undefined) return '—'
    if (typeof val === 'boolean') return val ? 'true' : 'false'
    if (typeof val === 'object') return JSON.stringify(val)
    return val
  }

  // ── Fetch ──────────────────────────────────────────────────────────────────────
  onMounted(() => store.getById(route.params.id))
</script>

<style scoped>
  .cursor-pointer {
    cursor: pointer;
  }
  .info-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .info-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
  }
  .info-label {
    font-size: 0.75rem;
    color: #9e9e9e;
    font-weight: 600;
    min-width: 90px;
    padding-top: 2px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .info-value {
    font-size: 0.875rem;
    color: #1c1c1e;
  }
  .json-box {
    padding: 12px;
    border-radius: 8px;
    font-size: 0.8rem;
    white-space: pre-wrap;
    border: 1px solid #e0e0e0;
    overflow-x: auto;
  }
  .gap-1 {
    gap: 4px;
  }
  .gap-2 {
    gap: 8px;
  }
  .gap-3 {
    gap: 12px;
  }
</style>
