<template>
  <AppDialog
    :model-value="modelValue"
    :title="$t('subscription.plan_feature_listings.manage_catalog')"
    :subtitle="$t('subscription.plan_feature_listings.manage_catalog_hint')"
    icon="mdi-format-list-bulleted"
    max-width="560"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="d-flex justify-end mb-4">
      <v-btn
        size="small"
        variant="tonal"
        rounded="lg"
        prepend-icon="mdi-plus"
        color="primary"
        @click="openCreate"
      >
        {{ $t('subscription.plan_feature_listings.add_feature') }}
      </v-btn>
    </div>

    <v-list density="compact">
      <v-list-item
        v-for="listing in catalogStore.items"
        :key="listing.id"
        :title="listing.label.en"
      >
        <template #subtitle>
          <v-chip size="x-small" class="mr-1">
            {{ $t(`subscription.plan_feature_listings.value_types.${listing.value_type}`) }}
          </v-chip>
          <span class="text-caption">{{ listing.key }}</span>
          <v-chip
            v-if="!listing.is_active"
            size="x-small"
            color="warning"
            variant="tonal"
            class="ml-1"
          >
            {{ $t('status.inactive') }}
          </v-chip>
        </template>
        <template #append>
          <v-btn icon="mdi-pencil-outline" size="small" variant="text" @click="openEdit(listing)" />
          <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="askDelete(listing)" />
        </template>
      </v-list-item>
    </v-list>

    <PlanFeatureListingFormDialog
      v-model="formDialog"
      :editing-listing="editingListing"
      :saving="saving"
      @submit="handleSubmit"
    />

    <template #actions>
      <v-spacer />
      <v-btn variant="tonal" rounded="lg" @click="emit('update:modelValue', false)">
        {{ $t('btn.close') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<script setup>
  import { ref, watch } from 'vue'
  import { useI18n } from 'vue-i18n'
  import { useAppUtils } from '@nong-official-dev/core'
  import AppDialog from '@/components/common/AppDialog.vue'
  import PlanFeatureListingFormDialog from '@/components/subscriptions/PlanFeatureListingFormDialog.vue'
  import { usePlanFeatureListingStore } from '@/stores/planFeatureListingStore'

  const props = defineProps({
    modelValue: { type: Boolean, default: false }
  })

  const emit = defineEmits(['update:modelValue'])

  const { t } = useI18n()
  const { confirm, notif } = useAppUtils()

  const catalogStore = usePlanFeatureListingStore()

  const formDialog = ref(false)
  const editingListing = ref(null)
  const saving = ref(false)

  watch(
    () => props.modelValue,
    open => {
      if (open) catalogStore.fetch()
    }
  )

  const openCreate = () => {
    editingListing.value = null
    formDialog.value = true
  }

  const openEdit = listing => {
    editingListing.value = listing
    formDialog.value = true
  }

  const handleSubmit = async data => {
    saving.value = true
    try {
      if (editingListing.value) {
        await catalogStore.update(editingListing.value.id, data)
        notif(t('subscription.plan_feature_listings.messages.updated'), { type: 'success' })
      } else {
        await catalogStore.create(data)
        notif(t('subscription.plan_feature_listings.messages.created'), { type: 'success' })
      }
      formDialog.value = false
    } finally {
      saving.value = false
    }
  }

  const askDelete = listing => {
    confirm({
      title: t('subscription.plan_feature_listings.confirm_delete.title'),
      message: t('subscription.plan_feature_listings.confirm_delete.message', { label: listing.label.en }),
      options: { type: 'warning', width: 400 },
      agree: async () => {
        await catalogStore.remove(listing.id)
        notif(t('subscription.plan_feature_listings.messages.deleted'), { type: 'success' })
      },
      cancel: () => {}
    })
  }
</script>
