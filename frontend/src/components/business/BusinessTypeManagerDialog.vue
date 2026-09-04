<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('business_type.manage_title')"
    :max-width="560"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="d-flex justify-end px-5 pb-3">
      <v-btn color="primary" variant="tonal" rounded="lg" prepend-icon="mdi-plus" @click="openCreate">
        {{ $t('business_type.add') }}
      </v-btn>
    </div>

    <v-list density="compact">
      <v-list-item v-for="item in store.businessTypes" :key="item.id">
        <template #prepend>
          <v-avatar size="32" rounded="lg" color="primary" variant="tonal" class="me-2">
            <v-icon size="16">{{ item.icon }}</v-icon>
          </v-avatar>
        </template>
        <v-list-item-title class="text-body-2 font-weight-medium">
          {{ item.name }}
        </v-list-item-title>
        <v-list-item-subtitle>{{ item.code }}</v-list-item-subtitle>
        <template #append>
          <v-chip
            v-if="item.category"
            :color="item.category === 'food' ? 'deep-orange' : 'blue'"
            variant="tonal"
            size="x-small"
            class="me-2"
          >
            {{ item.category === 'food' ? $t('business_type.category_food') : $t('business_type.category_mart') }}
          </v-chip>
          <v-chip
            :color="item.is_active ? 'success' : 'default'"
            :variant="item.is_active ? 'tonal' : 'outlined'"
            size="x-small"
            class="me-2"
          >
            {{ item.is_active ? $t('status.active') : $t('status.inactive') }}
          </v-chip>
          <v-btn icon="mdi-pencil-outline" size="small" variant="text" color="primary" @click="openEdit(item)" />
          <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="askDelete(item)" />
        </template>
      </v-list-item>

      <v-list-item v-if="!store.businessTypes.length">
        <v-list-item-title class="text-body-2 text-medium-emphasis text-center py-4">
          {{ $t('business_type.empty') }}
        </v-list-item-title>
      </v-list-item>
    </v-list>

    <!-- ── Create / Edit sub-dialog ─────────────────────────────────────── -->
    <AppDialog
      v-model="formDialog"
      :max-width="480"
      :title="isEdit ? $t('business_type.edit_title') : $t('business_type.create_title')"
      :loading="formLoading"
    >
      <v-form ref="formRef" @submit.prevent>
        <v-row dense>
          <!-- Icon picker -->
          <v-col cols="12">
            <div class="text-caption text-medium-emphasis mb-2 font-weight-medium text-uppercase" style="letter-spacing: 0.5px">
              {{ $t('business_type.icon') }}
            </div>
            <div class="d-flex flex-wrap gap-2 mb-1">
              <v-btn
                v-for="icon in iconOptions"
                :key="icon"
                :variant="form.icon === icon ? 'tonal' : 'outlined'"
                :color="form.icon === icon ? 'primary' : 'default'"
                size="small"
                rounded="lg"
                @click="form.icon = icon"
              >
                <v-icon>{{ icon }}</v-icon>
              </v-btn>
            </div>
          </v-col>

          <!-- Name -->
          <v-col cols="12">
            <v-text-field
              v-model="form.name"
              :label="$t('business_type.name_label')"
              variant="outlined"
              rounded="lg"
              :rules="[rules.required]"
              prepend-inner-icon="mdi-shape-outline"
              :placeholder="$t('business_type.name_placeholder')"
              @input="autoCode"
            />
          </v-col>

          <!-- Code -->
          <v-col cols="12">
            <v-text-field
              v-model="form.code"
              :label="$t('business_type.code_label')"
              variant="outlined"
              rounded="lg"
              :rules="[rules.required, rules.code]"
              prepend-inner-icon="mdi-identifier"
              :placeholder="$t('business_type.code_placeholder')"
              :hint="$t('business_type.code_hint')"
              persistent-hint
              @input="form.code = form.code.toUpperCase().replace(/[^A-Z0-9_]/g, '')"
            />
          </v-col>

          <!-- Category -->
          <v-col cols="12">
            <v-select
              v-model="form.category"
              :items="categoryOptions"
              :label="$t('business_type.category_label')"
              variant="outlined"
              rounded="lg"
              :rules="[rules.required]"
              prepend-inner-icon="mdi-shape-outline"
              :hint="$t('business_type.category_hint')"
              persistent-hint
            />
          </v-col>

          <!-- Status -->
          <v-col cols="12">
            <v-card rounded="lg" variant="outlined" class="pa-4">
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-body-2 font-weight-medium">{{ $t('status.active') }}</div>
                  <div class="text-caption text-medium-emphasis">{{ $t('business_type.enable_hint') }}</div>
                </div>
                <v-switch v-model="form.is_active" color="primary" hide-details density="compact" inset />
              </div>
            </v-card>
          </v-col>
        </v-row>
      </v-form>

      <template #actions="{ loading }">
        <v-btn variant="tonal" rounded="lg" :disabled="loading" @click="closeForm">
          {{ $t('btn.cancel') }}
        </v-btn>
        <v-btn
          :color="isEdit ? 'primary' : 'success'"
          variant="flat"
          rounded="lg"
          :prepend-icon="isEdit ? 'mdi-check' : 'mdi-plus'"
          :loading="loading"
          @click="submitForm"
        >
          {{ isEdit ? $t('btn.update') : $t('btn.create') }}
        </v-btn>
      </template>
    </AppDialog>

    <template #actions>
      <v-btn variant="tonal" rounded="lg" @click="emit('update:modelValue', false)">
        {{ $t('btn.cancel') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, reactive, computed, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useBusinessTypeStore } from '@/stores/businessTypeStore'
  import { AppDialog, useAppUtils } from '@nong-official-dev/core'

  const props = defineProps({
    modelValue: { type: Boolean, default: false }
  })
  const emit = defineEmits(['update:modelValue'])

  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()
  const store = useBusinessTypeStore()

  // Refetch every time this opens, same as photo-studio-saas's
  // CustomerTagManagerDialog — small reference dataset, cheap to always
  // re-fetch fresh rather than trust whatever's already cached.
  watch(
    () => props.modelValue,
    open => {
      if (open) store.fetchBusinessTypes()
    }
  )

  const formDialog = ref(false)
  const formLoading = ref(false)
  const formRef = ref(null)
  const isEdit = computed(() => !!form.id)

  const defaultForm = () => ({
    id: null,
    name: '',
    code: '',
    icon: 'mdi-silverware',
    category: 'food',
    is_active: true
  })
  const form = reactive(defaultForm())

  const iconOptions = [
    'mdi-silverware', 'mdi-coffee', 'mdi-store', 'mdi-cart', 'mdi-pizza',
    'mdi-noodles', 'mdi-fish', 'mdi-shopping', 'mdi-bag-personal', 'mdi-cake', 'mdi-cup'
  ]

  // Drives authStore.isFood/isMart app-wide (see authStore.js) — deliberately
  // just these two, matching every existing food-vs-mart branch in the app
  // today. Not free-text: a typo'd category would silently fail every one
  // of those checks with no error surfaced anywhere.
  const categoryOptions = computed(() => [
    { title: t('business_type.category_food'), value: 'food' },
    { title: t('business_type.category_mart'), value: 'mart' }
  ])

  const rules = {
    required: v => !!v || t('validation.required'),
    code: v => /^[A-Z0-9_]+$/.test(v) || t('business_type.code_rule')
  }

  const autoCode = () => {
    if (!isEdit.value) {
      form.code = form.name.toUpperCase().replace(/\s+/g, '_').replace(/[^A-Z0-9_]/g, '')
    }
  }

  const openCreate = () => {
    Object.assign(form, defaultForm())
    formDialog.value = true
  }

  const openEdit = item => {
    Object.assign(form, { ...item })
    formDialog.value = true
  }

  const closeForm = () => {
    formDialog.value = false
  }

  watch(formDialog, val => {
    if (!val) {
      Object.assign(form, defaultForm())
      formRef.value?.reset()
    }
  })

  const submitForm = async () => {
    formLoading.value = true
    try {
      if (isEdit.value) {
        await store.updateBusinessType(form.id, { ...form })
        notif(t('business_type.messages.updated'), { type: 'success' })
      } else {
        await store.createBusinessType({ ...form })
        notif(t('business_type.messages.created'), { type: 'success' })
      }
      closeForm()
    } catch (e) {
      notif(e?.response?.data?.message || t('business_type.messages.save_failed'), { type: 'error' })
    } finally {
      formLoading.value = false
    }
  }

  const askDelete = item => {
    confirm({
      title: t('business_type.delete_title'),
      message: t('business_type.delete_message', { name: item.name }),
      options: { type: 'warning', width: 480 },
      agree: async () => {
        try {
          await store.deleteBusinessType(item.id)
          notif(t('business_type.messages.deleted'), { type: 'success' })
        } catch (e) {
          notif(e?.response?.data?.message || t('business_type.messages.delete_failed'), { type: 'error' })
        }
      },
      cancel: () => {}
    })
  }
</script>
