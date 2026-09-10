<template>
  <v-row dense>
    <v-col cols="12" sm="8">
      <v-text-field
        :model-value="name"
        :label="$t('tenant_create.field.business_name')"
        variant="outlined"
        rounded="lg"
        prepend-inner-icon="mdi-domain"
        :rules="[requiredRule]"
        :error-messages="serverErrors.name"
        maxlength="150"
        @update:model-value="onNameChange"
      />
    </v-col>
    <v-col cols="12" sm="4">
      <v-text-field
        :model-value="slug"
        :label="$t('tenant_create.field.slug')"
        variant="outlined"
        rounded="lg"
        prepend-inner-icon="mdi-link-variant"
        :readonly="!editingSlug"
        :bg-color="!editingSlug ? 'grey-lighten-2' : undefined"
        :hint="$t('tenant_create.slug_hint')"
        :error-messages="serverErrors.slug"
        persistent-hint
        maxlength="100"
        @update:model-value="v => $emit('update:slug', v)"
      >
        <template #append-inner>
          <v-tooltip :text="editingSlug ? $t('tenant_create.lock_slug') : $t('tenant_create.edit_slug')">
            <template #activator="{ props: tp }">
              <v-icon
                v-bind="tp"
                :icon="editingSlug ? 'mdi-lock-open-outline' : 'mdi-lock-outline'"
                size="18"
                class="cursor-pointer text-medium-emphasis"
                @click="$emit('update:editingSlug', !editingSlug)"
              />
            </template>
          </v-tooltip>
        </template>
      </v-text-field>
    </v-col>

    <v-col cols="12" sm="6">
      <v-select
        :model-value="businessTypeId"
        :items="businessTypes"
        :loading="buTypesLoading"
        item-title="name"
        item-value="id"
        :label="$t('tenant_create.field.business_type')"
        variant="outlined"
        rounded="lg"
        prepend-inner-icon="mdi-store-outline"
        :rules="[requiredRule]"
        :error-messages="serverErrors.business_type_id"
        @update:model-value="v => $emit('update:businessTypeId', v)"
      >
        <template #item="{ props: p, item }">
          <v-list-item v-bind="p">
            <template #prepend>
              <v-avatar
                :color="item.raw.color"
                variant="tonal"
                size="28"
                rounded="md"
                class="mr-2"
              >
                <v-icon :icon="item.raw.icon" size="15" />
              </v-avatar>
            </template>
            <template #append>
              <span class="text-caption text-medium-emphasis">{{ item.raw.code }}</span>
            </template>
          </v-list-item>
        </template>
        <template #selection="{ item }">
          <div class="d-flex align-center ga-2">
            <v-avatar :color="item.raw.color" variant="tonal" size="22" rounded="sm">
              <v-icon :icon="item.raw.icon" size="13" />
            </v-avatar>
            <span class="text-body-2">{{ item.raw.name }}</span>
          </div>
        </template>
      </v-select>
    </v-col>

    <v-col cols="12" sm="6">
      <v-select
        :model-value="currency"
        :items="currencyOptions"
        item-title="label"
        item-value="value"
        :label="$t('tenant_create.field.currency')"
        variant="outlined"
        rounded="lg"
        prepend-inner-icon="mdi-currency-usd"
        @update:model-value="v => $emit('update:currency', v)"
      />
    </v-col>

    <v-col cols="12" sm="6">
      <v-text-field
        :model-value="logoUrl"
        :label="$t('tenant_create.field.logo_url')"
        variant="outlined"
        rounded="lg"
        prepend-inner-icon="mdi-image-outline"
        @update:model-value="v => $emit('update:logoUrl', v)"
      />
    </v-col>

    <v-col cols="12" sm="6">
      <v-color-input
        :model-value="primaryColor"
        color-pip
        :label="$t('tenant_create.field.brand_color')"
        variant="outlined"
        density="comfortable"
        pip-location="prepend-inner"
        @update:model-value="v => $emit('update:primaryColor', v)"
      />
    </v-col>
  </v-row>
</template>

<script setup>
  import { slugify } from '@/utils/slugify'

  const props = defineProps({
    name: { type: String, default: '' },
    slug: { type: String, default: '' },
    businessTypeId: { type: String, default: null },
    logoUrl: { type: String, default: '' },
    primaryColor: { type: String, default: '#6366f1' },
    currency: { type: String, default: 'USD' },
    editingSlug: { type: Boolean, default: false },
    businessTypes: { type: Array, default: () => [] },
    buTypesLoading: { type: Boolean, default: false },
    currencyOptions: { type: Array, default: () => [] },
    serverErrors: { type: Object, default: () => ({}) },
    requiredRule: { type: Function, required: true }
  })

  const emit = defineEmits([
    'update:name',
    'update:slug',
    'update:businessTypeId',
    'update:logoUrl',
    'update:primaryColor',
    'update:currency',
    'update:editingSlug',
    'clear-error'
  ])

  // Auto-slugify from the business name as long as the user hasn't
  // unlocked the slug field to edit it manually.
  const onNameChange = val => {
    emit('update:name', val)
    emit('clear-error', 'name')
    if (!props.editingSlug) emit('update:slug', slugify(val))
  }
</script>

<style scoped>
  .cursor-pointer {
    cursor: pointer;
  }
</style>
