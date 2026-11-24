<template>
  <v-card rounded="lg" elevation="1">
    <v-card-text class="pa-0">
      <!-- User info -->
      <div class="px-4 pt-4 pb-3">
        <div class="d-flex align-center gap-3">
          <v-avatar :color="avatarColor" size="48" rounded="lg">
            <v-img v-if="user?.avatar_url" :src="user.avatar_url" cover />
            <span v-else class="text-body-medium font-weight-black text-white">
              {{ initials }}
            </span>
          </v-avatar>
          <div class="min-w-0 flex-1 overflow-hidden">
            <div class="text-body-medium font-weight-bold text-truncate">
              {{ user?.first_name }} {{ user?.last_name }}
            </div>
            <div class="text-body-small text-medium-emphasis text-truncate">
              {{ user?.email }}
            </div>
          </div>
        </div>
      </div>

      <v-divider />

      <!-- Profile -->
      <div class="pa-2">
        <v-list-item
          prepend-icon="mdi-account-circle-outline"
          :title="t('account.tabs.profile')"
          rounded="lg"
          link
          @click="$emit('profile-click')"
        >
          <template #append>
            <v-icon size="14" color="medium-emphasis">mdi-chevron-right</v-icon>
          </template>
        </v-list-item>
      </div>

      <v-divider />

      <!-- Logout -->
      <div class="pa-3">
        <v-btn
          block
          variant="tonal"
          rounded="lg"
          color="error"
          prepend-icon="mdi-logout"
          @click="$emit('logout')"
        >
          {{ t('common.sign_out') || 'Sign Out' }}
        </v-btn>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
  import { useI18n } from 'vue-i18n'

  const { t } = useI18n()

  defineProps({
    user: Object,
    initials: String,
    avatarColor: String
  })

  defineEmits(['profile-click', 'logout'])
</script>
