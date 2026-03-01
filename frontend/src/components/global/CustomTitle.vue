<template>
  <div
    class="d-flex align-center justify-space-between mb-6 w-100"
    :class="{ 'flex-column text-center': centeredTitle }"
  >
    <div class="d-flex align-center" :class="{ 'flex-column': centeredTitle }">
      <v-avatar
        v-if="icon"
        color="primary"
        variant="tonal"
        rounded="lg"
        size="48"
        :class="centeredTitle ? 'mb-3' : 'mr-4'"
      >
        <v-icon :icon="icon" size="24" />
      </v-avatar>

      <div :class="{ 'd-flex flex-column align-center': centeredTitle }">
        <h2 class="text-h5 font-weight-bold text-high-emphasis mb-0">
          <slot>{{ title }}</slot>
        </h2>

        <p
          v-if="subtitle || $slots.subtitle"
          class="text-body-2 text-medium-emphasis mb-0 mt-1"
        >
          <slot name="subtitle">{{ subtitle }}</slot>
        </p>
      </div>
    </div>

    <div
      v-if="$slots.right || rightIcon"
      class="d-flex align-center"
      :class="{ 'mt-4': centeredTitle }"
    >
      <slot name="right">
        <v-btn
          v-if="rightIcon"
          :icon="rightIcon"
          variant="text"
          color="medium-emphasis"
        />
      </slot>
    </div>
  </div>
</template>

<script setup>
  defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    icon: { type: String, required: false },
    rightIcon: { type: String },
    centeredTitle: { type: Boolean, default: false }
  })
</script>

<style scoped>
  /* Removed v-card dependency to allow it to sit naturally in any layout */
</style>
