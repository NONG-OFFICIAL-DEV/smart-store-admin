<template>
  <div class="d-flex align-center mb-6 w-100">
    <v-btn
      v-if="showBack"
      icon="mdi-arrow-left"
      variant="tonal"
      rounded="lg"
      size="small"
      class="mr-4"
      color="primary"
      @click="onBack"
    />

    <div class="d-flex flex-column min-w-0">
      <v-breadcrumbs :items="breadcrumbs" density="compact" class="pa-0 mb-1">
        <template #divider>
          <v-icon icon="mdi-chevron-right" size="14" color="grey-lighten-1" />
        </template>
        <template #item="{ item }">
          <v-breadcrumbs-item
            :to="item.to"
            :disabled="item.disabled || !item.to"
            class="text-caption font-weight-bold px-0"
            :class="{ 'text-primary': item.to && !item.disabled }"
          >
            {{ item.title }}
          </v-breadcrumbs-item>
        </template>
      </v-breadcrumbs>

      <div class="d-flex align-center gap-2">
        <h2
          class="text-h6 font-weight-black letter-spacing-tight text-truncate"
        >
          {{ title }}
        </h2>
        <slot name="title-after" />
      </div>
    </div>

    <v-spacer />

    <div class="d-flex align-center gap-2 ml-4">
      <slot name="right" />
    </div>
  </div>
</template>

<script setup>
  import { useRouter } from 'vue-router'

  const router = useRouter()

  const props = defineProps({
    title: { type: String, required: true },
    breadcrumbs: { type: Array, default: () => [] },
    showBack: { type: Boolean, default: false },
    backTo: { type: String, default: null }
  })

  const onBack = () => {
    if (props.backTo) {
      router.push(props.backTo)
    } else {
      router.back()
    }
  }
</script>

<style scoped>
  .letter-spacing-tight {
    letter-spacing: -0.025em !important;
  }

  .gap-2 {
    gap: 8px;
  }

  /* Ensure breadcrumbs don't look like standard links */
  :deep(.v-breadcrumbs-item--disabled) {
    opacity: 1 !important;
    color: #94a3b8 !important; /* Slate 400 */
  }

  :deep(.v-breadcrumbs-item:not(.v-breadcrumbs-item--disabled)) {
    opacity: 0.7;
    transition: opacity 0.2s;
  }

  :deep(.v-breadcrumbs-item:not(.v-breadcrumbs-item--disabled):hover) {
    opacity: 1;
    text-decoration: underline;
  }
</style>
