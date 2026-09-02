<template>
  <AppDialog
    v-model="model"
    :max-width="680"
    :persistent="false"
    :scrollable="false"
    :title="$t('roles.assign_permissions')"
    :subtitle="role?.name"
    icon="mdi-key-variant"
    color="warning"
    :loading="loading"
    :submit-text="$t('roles.save_permissions')"
    submit-icon="mdi-content-save"
    :body-class="'pa-4'"
    :body-style="{ maxHeight: '460px', overflowY: 'auto' }"
    @submit="handleSave"
  >
    <template #header-extra>
      <div class="d-flex align-center px-4 pt-3">
        <v-chip color="primary" variant="tonal" size="small">
          {{ $t('roles.selected_count', { n: selectedIds.length }) }}
        </v-chip>
      </div>

      <!-- Quick templates — a small shop shouldn't have to hand-pick from
           every permission code; these pre-check a sensible bundle, still
           freely adjustable below before saving. -->
      <div class="d-flex align-center gap-2 flex-wrap px-4 pt-3">
        <span class="text-caption text-medium-emphasis me-1">
          {{ $t('roles.templates.label') }}
        </span>
        <v-chip
          v-for="tpl in templates"
          :key="tpl.key"
          size="small"
          variant="tonal"
          color="primary"
          prepend-icon="mdi-auto-fix"
          @click="applyTemplate(tpl)"
        >
          {{ tpl.label }}
        </v-chip>
      </div>

      <!-- Search -->
      <div class="pa-4 pb-4">
        <v-text-field
          v-model="permSearch"
          :placeholder="$t('roles.search_permissions_placeholder')"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          hide-details
          clearable
          rounded="lg"
        />
      </div>
    </template>

    <!-- Grouped permissions -->
    <div
          v-for="(group, groupName) in filteredGrouped"
          :key="groupName"
          class="mb-5"
        >
          <div class="d-flex align-center justify-space-between mb-2">
            <div class="d-flex align-center gap-2">
              <v-chip
                :color="groupColor(groupName)"
                variant="flat"
                size="small"
                rounded="lg"
                class="me-2"
              >
                <v-icon icon="mdi-folder" size="13" class="mr-1" />
                {{ groupName }}
              </v-chip>
              <span class="text-caption text-medium-emphasis">
                {{ $t('roles.permissions_count', { n: group.length }) }}
              </span>
            </div>
            <v-btn
              size="x-small"
              variant="text"
              :color="groupAllSelected(group) ? 'error' : 'primary'"
              @click="toggleGroup(group)"
            >
              {{ groupAllSelected(group) ? $t('btn.deselect_all') : $t('btn.select_all') }}
            </v-btn>
          </div>

          <v-row dense>
            <v-col v-for="perm in group" :key="perm.id" cols="12" sm="6">
              <v-card
                rounded="lg"
                elevation="0"
                :color="
                  selectedIds.includes(perm.id)
                    ? groupColor(groupName)
                    : undefined
                "
                :variant="selectedIds.includes(perm.id) ? 'tonal' : 'outlined'"
                class="perm-card pa-2"
                @click="togglePerm(perm.id)"
              >
                <div class="d-flex align-center gap-2">
                  <v-checkbox
                    :model-value="selectedIds.includes(perm.id)"
                    :color="groupColor(groupName)"
                    density="compact"
                    hide-details
                    class="me-2"
                  />
                  <div>
                    <div class="text-body-2 font-weight-medium">
                      {{ perm.code }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ perm.description }}
                    </div>
                  </div>
                </div>
              </v-card>
            </v-col>
          </v-row>
        </div>

    <div
      v-if="!Object.keys(filteredGrouped).length"
      class="text-center py-8 text-medium-emphasis"
    >
      <v-icon icon="mdi-key-off" size="40" color="grey-lighten-2" />
      <p class="text-body-2 mt-2">{{ $t('roles.no_permissions_found') }}</p>
    </div>
  </AppDialog>
</template>

<script setup>
  import { ref, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import AppDialog from '@/components/common/AppDialog.vue'

  const { t } = useI18n()

  // ── Props & Emits ─────────────────────────────────────────────────────────────
  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    role: { type: Object, default: null }, // the role being edited
    permissions: { type: Array, default: () => [] }, // all available permissions
    assigned: { type: Array, default: () => [] }, // currently assigned permission ids
    loading: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue', 'saved'])

  // ── Model ──────────────────────────────────────────────────────────────────────
  const model = computed({
    get: () => props.modelValue,
    set: val => emit('update:modelValue', val)
  })

  // ── State ─────────────────────────────────────────────────────────────────────
  const permSearch = ref('')
  const selectedIds = ref([])

  // ── Sync assigned ids when dialog opens ──────────────────────────────────────
  watch(
    () => props.assigned,
    val => {
      selectedIds.value = [...val]
    },
    { immediate: true }
  )

  watch(
    () => props.modelValue,
    open => {
      if (open) {
        selectedIds.value = [...props.assigned]
        permSearch.value = ''
      }
    }
  )

  // ── Grouped permissions ───────────────────────────────────────────────────────
  const groupedAll = computed(() => {
    return props.permissions.reduce((acc, p) => {
      if (!acc[p.group]) acc[p.group] = []
      acc[p.group].push(p)
      return acc
    }, {})
  })

  const filteredGrouped = computed(() => {
    if (!permSearch.value) return groupedAll.value
    const q = permSearch.value.toLowerCase()
    const result = {}
    for (const [group, perms] of Object.entries(groupedAll.value)) {
      const filtered = perms.filter(
        p =>
          p.code.toLowerCase().includes(q) ||
          (p.description || '').toLowerCase().includes(q)
      )
      if (filtered.length) result[group] = filtered
    }
    return result
  })

  // ── Color map ─────────────────────────────────────────────────────────────────
  const colorList = [
    'primary',
    'success',
    'warning',
    'error',
    'secondary',
    'teal',
    'purple',
    'orange',
    'pink',
    'indigo'
  ]
  const colorCache = {}
  const groupColor = g => {
    if (!colorCache[g]) {
      colorCache[g] =
        colorList[Object.keys(colorCache).length % colorList.length]
    }
    return colorCache[g]
  }

  // ── Quick templates ───────────────────────────────────────────────────────────
  // Sensible starting bundles for the two roles almost every small shop
  // needs — Owner already has full access automatically, so it's excluded.
  const templates = computed(() => [
    {
      key: 'cashier',
      label: t('roles.templates.cashier'),
      codes: ['orders.manage', 'payments.manage', 'cash_drawers.manage', 'customers.manage']
    },
    {
      key: 'manager',
      label: t('roles.templates.manager'),
      codes: [
        'staff.manage', 'shifts.manage', 'menus.manage', 'categories.manage',
        'products.manage', 'floor_plans.manage', 'reservations.manage', 'orders.manage',
        'kitchen.manage', 'payments.manage', 'cash_drawers.manage', 'suppliers.manage',
        'ingredients.manage', 'inventory.manage', 'purchase_orders.manage', 'customers.manage',
        'promotions.manage', 'reports.view'
      ]
    }
  ])

  const applyTemplate = tpl => {
    selectedIds.value = props.permissions
      .filter(p => tpl.codes.includes(p.code))
      .map(p => p.id)
  }

  // ── Toggle helpers ────────────────────────────────────────────────────────────
  const groupAllSelected = group =>
    group.every(p => selectedIds.value.includes(p.id))

  const toggleGroup = group => {
    if (groupAllSelected(group)) {
      selectedIds.value = selectedIds.value.filter(
        id => !group.find(p => p.id === id)
      )
    } else {
      group.forEach(p => {
        if (!selectedIds.value.includes(p.id)) selectedIds.value.push(p.id)
      })
    }
  }

  const togglePerm = id => {
    const idx = selectedIds.value.indexOf(id)
    if (idx === -1) selectedIds.value.push(id)
    else selectedIds.value.splice(idx, 1)
  }

  // ── Actions ───────────────────────────────────────────────────────────────────
  const handleSave = () => {
    emit('saved', {
      role_id: props.role?.id,
      permission_ids: selectedIds.value
    })
  }
</script>

<style scoped>
  .perm-card {
    cursor: pointer;
    transition: all 0.15s;
  }
  .perm-card:hover {
    transform: translateY(-1px);
  }
</style>
