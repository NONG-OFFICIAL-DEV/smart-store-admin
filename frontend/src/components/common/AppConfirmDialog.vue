<template>
  <v-dialog
    v-model="dialog"
    :max-width="options.width"
    persistent
    @keydown.esc="cancel"
  >
    <v-card rounded="lg">
      <v-card-title class="pa-5 pb-3">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <v-avatar :color="options.type" size="40" rounded="lg" variant="tonal" class="me-4">
              <v-icon :icon="resolvedIcon" size="20" />
            </v-avatar>
            <div class="text-body-1 font-weight-bold">{{ title }}</div>
          </div>
          <v-btn icon="mdi-close" size="small" variant="text" @click="cancel" />
        </div>
      </v-card-title>

      <v-card-text v-show="!!message" class="px-5 pt-0 pb-4">
        <span v-html="message"></span>
        <div class="text-caption text-medium-emphasis mt-2">
          {{ $t('common.action_cannot_be_undone') }}
        </div>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4">
        <v-btn variant="tonal" rounded="lg" ref="btnNo" @click="cancel">
          {{ $t('btn.cancel') }}
        </v-btn>
        <v-spacer />
        <v-btn :color="options.type" variant="flat" rounded="lg" @click="agree">
          {{ $t('btn.yes') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  import { ref, computed, inject, onMounted } from 'vue'

  const dialog = ref(false)
  const title = ref(null)
  const message = ref(null)

  const options = ref({
    type: 'error',
    width: 420,
    icon: null
  })

  const ICONS_BY_TYPE = {
    error: 'mdi-alert-circle-outline',
    warning: 'mdi-alert-outline',
    info: 'mdi-information-outline',
    success: 'mdi-check-circle-outline'
  }

  const resolvedIcon = computed(
    () => options.value.icon || ICONS_BY_TYPE[options.value.type] || 'mdi-help-circle-outline'
  )

  let agreeCallback = () => {}
  let cancelCallback = () => {}

  function open({ title: t, message: m, options: o, agree, cancel }) {
    dialog.value = true
    title.value = t
    message.value = m
    options.value = { ...options.value, ...o }
    agreeCallback = agree || (() => {})
    cancelCallback = cancel || (() => {})
  }

  function agree() {
    agreeCallback()
    dialog.value = false
  }

  function cancel() {
    cancelCallback()
    dialog.value = false
  }

  const state = inject('coreState')

  onMounted(() => {
    state.confirmRef = { open }
  })
</script>
