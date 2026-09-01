<template>
  <v-container fluid class="pa-0">
    <custom-title
      :icon="activeTabMeta.icon"
      :title="t('workforce.title')"
      :subtitle="activeTabMeta.subtitle"
    >
      <template #right>
        <v-btn
          color="primary"
          :prepend-icon="activeTabMeta.actionIcon"
          rounded="lg"
          elevation="0"
          @click="triggerCreate"
        >
          {{ activeTabMeta.actionLabel }}
        </v-btn>
      </template>
    </custom-title>

    <v-tabs v-model="activeTab" color="primary" class="mb-4">
      <v-tab v-for="tab in visibleTabs" :key="tab.key" :value="tab.key">
        {{ tab.label }}
      </v-tab>
    </v-tabs>

    <v-window v-model="activeTab">
      <v-window-item value="staff">
        <StaffManagement ref="staffRef" hide-header />
      </v-window-item>
      <v-window-item value="shifts">
        <ShiftManagement ref="shiftsRef" hide-header />
      </v-window-item>
      <v-window-item value="assignments">
        <ShiftAssignment ref="assignmentsRef" hide-header />
      </v-window-item>
    </v-window>
  </v-container>
</template>

<script setup>
  import { ref, computed } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useRoute } from 'vue-router'
  import { useAuthStore } from '@/stores/authStore'
  import StaffManagement from '@/views/staff/StaffManagement.vue'
  import ShiftManagement from '@/views/staff/ShiftManagement.vue'
  import ShiftAssignment from '@/views/staff/ShiftAssignment.vue'

  const { t } = useI18n()
  const route = useRoute()
  const authStore = useAuthStore()

  const allTabs = [
    {
      key: 'staff',
      label: t('workforce.tabs.staff'),
      icon: 'mdi-account-group',
      subtitle: t('staff.subtitle'),
      actionLabel: t('btn.create'),
      actionIcon: 'mdi-plus',
      permission: 'staff.manage',
      ref: 'staffRef'
    },
    {
      key: 'shifts',
      label: t('workforce.tabs.shifts'),
      icon: 'mdi-calendar-clock',
      subtitle: t('shifts.subtitle'),
      actionLabel: t('btn.create'),
      actionIcon: 'mdi-plus',
      permission: 'shifts.manage',
      ref: 'shiftsRef'
    },
    {
      key: 'assignments',
      label: t('workforce.tabs.assignments'),
      icon: 'mdi-calendar-account-outline',
      subtitle: t('shift_assignments.subtitle'),
      actionLabel: t('btn.assign'),
      actionIcon: 'mdi-account-plus',
      permission: 'shifts.manage',
      ref: 'assignmentsRef'
    }
  ]

  const visibleTabs = computed(() => allTabs.filter(tab => authStore.can(tab.permission)))

  const requestedTab = typeof route.query.tab === 'string' ? route.query.tab : null
  const activeTab = ref(
    visibleTabs.value.find(t => t.key === requestedTab)?.key ?? visibleTabs.value[0]?.key
  )

  const activeTabMeta = computed(
    () => allTabs.find(tab => tab.key === activeTab.value) ?? allTabs[0]
  )

  const staffRef = ref(null)
  const shiftsRef = ref(null)
  const assignmentsRef = ref(null)
  const refsByKey = { staff: staffRef, shifts: shiftsRef, assignments: assignmentsRef }

  function triggerCreate() {
    refsByKey[activeTab.value]?.value?.openCreate?.()
  }

  // ShiftManagement's "assign staff" row action deep-links here with
  // ?tab=assignments&shift_id=... via the old /shift-assignments redirect —
  // ShiftAssignment.vue itself still reads route.query.shift_id/staff_id on
  // its own mount, so no extra wiring is needed beyond picking the tab above.
</script>
