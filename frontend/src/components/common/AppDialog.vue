<template>
  <v-dialog
    :model-value="modelValue"
    :max-width="maxWidth"
    :persistent="persistent"
    :scrollable="scrollable"
    @update:model-value="onUpdateModel"
  >
    <v-card rounded="lg">
      <!-- ── Header ─────────────────────────────────────────────────────── -->
      <v-card-title class="pa-5 pb-3">
        <div class="d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-3">
            <v-avatar
              v-if="icon"
              :color="color"
              size="40"
              rounded="lg"
              variant="tonal"
              class="me-4"
            >
              <v-icon :icon="icon" size="20" />
            </v-avatar>
            <div>
              <div class="text-body-1 font-weight-bold">{{ title }}</div>
              <div
                v-if="subtitle"
                class="text-caption text-medium-emphasis"
              >
                {{ subtitle }}
              </div>
            </div>
          </div>
          <v-btn
            icon="mdi-close"
            size="small"
            variant="text"
            :disabled="loading"
            @click="close"
          />
        </div>
      </v-card-title>

      <slot name="header-extra" />

      <v-divider />

      <!-- ── Body ───────────────────────────────────────────────────────── -->
      <v-card-text :class="bodyClass" :style="bodyStyle">
        <slot />
      </v-card-text>

      <v-divider />

      <!-- ── Actions ────────────────────────────────────────────────────── -->
      <v-card-actions v-if="!hideActions" class="pa-4 gap-2">
        <slot name="actions">
          <span v-if="errorMessage" class="text-caption text-error">
            <v-icon icon="mdi-alert-circle-outline" size="14" class="mr-1" />
            {{ errorMessage }}
          </span>
          <v-spacer />
          <v-btn
            variant="tonal"
            rounded="lg"
            :disabled="loading"
            @click="close"
          >
            {{ cancelText || $t('btn.cancel') }}
          </v-btn>
          <v-btn
            v-if="!hideSubmit"
            :color="color"
            variant="flat"
            rounded="lg"
            :prepend-icon="submitIcon"
            :loading="loading"
            :disabled="disableSubmit"
            @click="$emit('submit')"
          >
            {{ submitText }}
          </v-btn>
        </slot>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    // mdi icon name shown in the avatar badge, e.g. "mdi-package-variant"
    icon: { type: String, default: '' },
    // drives the avatar color and the primary action button color
    color: { type: String, default: 'primary' },
    maxWidth: { type: [String, Number], default: 680 },
    persistent: { type: Boolean, default: true },
    scrollable: { type: Boolean, default: true },
    loading: { type: Boolean, default: false },
    submitText: { type: String, default: '' },
    submitIcon: { type: String, default: '' },
    cancelText: { type: String, default: '' },
    hideActions: { type: Boolean, default: false },
    hideSubmit: { type: Boolean, default: false },
    disableSubmit: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' },
    bodyClass: { type: String, default: 'px-6 py-5' },
    bodyStyle: { type: [String, Object], default: '' }
  })

  const emit = defineEmits(['update:modelValue', 'close', 'submit'])

  function onUpdateModel(val) {
    emit('update:modelValue', val)
    if (!val) emit('close')
  }

  function close() {
    if (props.loading) return
    emit('update:modelValue', false)
    emit('close')
  }
</script>
