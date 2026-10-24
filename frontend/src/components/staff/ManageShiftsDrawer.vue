<template>
  <v-navigation-drawer v-model="model" location="right" width="420" temporary>
    <div class="d-flex align-center justify-space-between pa-4">
      <span class="text-h6 font-weight-bold">{{ t('staff.schedule.manage_shifts') }}</span>
      <v-btn icon="mdi-close" variant="text" @click="model = false" />
    </div>
    <v-divider />

    <div class="pa-4">
      <v-btn
        block
        color="primary"
        variant="flat"
        rounded="lg"
        prepend-icon="mdi-plus"
        class="mb-4"
        @click="openCreate"
      >
        {{ t('btn.create') }}
      </v-btn>

      <v-list v-if="shiftStore.shiftList.length" lines="two">
        <template v-for="s in shiftStore.shiftList" :key="s.id">
          <v-list-item :class="{ 'opacity-60': !s.is_active }">
            <template #prepend>
              <v-icon icon="mdi-circle" size="14" :color="s.color" />
            </template>
            <v-list-item-title class="font-weight-bold">{{ s.name }}</v-list-item-title>
            <v-list-item-subtitle>
              {{ s.start_time?.slice(0, 5) }} – {{ s.end_time?.slice(0, 5) }} · {{ s.duration }}
            </v-list-item-subtitle>
            <template #append>
              <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="openEdit(s)" />
              <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="confirmDelete(s)" />
            </template>
          </v-list-item>
          <v-divider />
        </template>
      </v-list>
      <div v-else class="text-center text-medium-emphasis py-8">
        {{ t('shifts.empty') }}
      </div>
    </div>

    <ShiftFormDialog v-model="formDialog" :item="selectedItem" :loading="saving" @save="handleSave" />
  </v-navigation-drawer>
</template>

<script setup>
  import { ref, computed, onMounted } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils } from '@nong-official-dev/core'
  import { useShiftStore } from '@/stores/shiftStore'
  import ShiftFormDialog from '@/components/staff/ShiftFormDialog.vue'

  const props = defineProps({
    modelValue: { type: Boolean, default: false }
  })
  const emit = defineEmits(['update:modelValue', 'changed'])

  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const shiftStore = useShiftStore()

  const model = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v)
  })

  const formDialog = ref(false)
  const selectedItem = ref(null)
  const saving = ref(false)

  const openCreate = () => {
    selectedItem.value = null
    formDialog.value = true
  }
  const openEdit = item => {
    selectedItem.value = { ...item }
    formDialog.value = true
  }

  const confirmDelete = item => {
    confirm({
      title: t('shifts.confirm_delete.title'),
      message: t('shifts.confirm_delete.message', { name: item.name }),
      options: { type: 'warning', width: 480 },
      agree: async () => {
        try {
          await shiftStore.deleteShift(item.id)
          notif(t('shifts.messages.removed'), { type: 'success' })
          emit('changed')
        } catch {
          notif(t('shifts.messages.remove_failed'), { type: 'error' })
        }
      }
    })
  }

  const handleSave = async payload => {
    saving.value = true
    try {
      payload.id
        ? await shiftStore.updateShift(payload.id, payload)
        : await shiftStore.createShift(payload)
      formDialog.value = false
      notif(payload.id ? t('shifts.messages.updated') : t('shifts.messages.created'), { type: 'success' })
      emit('changed')
    } catch {
      notif(t('shifts.messages.save_failed'), { type: 'error' })
    } finally {
      saving.value = false
    }
  }

  onMounted(() => {
    if (!shiftStore.shiftList.length) shiftStore.fetchShifts({ perPage: -1 })
  })
</script>
