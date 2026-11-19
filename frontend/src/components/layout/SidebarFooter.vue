<script setup>
import { computed } from 'vue'
import ShopBranchSwitcher from './ShopBranchSwitcher.vue'
// ⬇️ Use your EXISTING components (same ones AppBarActions uses)
import LanguageSwitch from '@/components/common/LanguageSwitch.vue'
// import NotificationBell from '@/components/common/NotificationBell.vue'

const props = defineProps({
  user: Object,
  roleName: String,
  rail: Boolean
})
const emit = defineEmits(['logout'])

const initials = computed(() => {
  const name = props.user?.name || ''
  return name.split(' ').map(p => p[0]).join('').slice(0, 2).toUpperCase() || '?'
})
</script>

<template>
  <div class="sb-footer" :class="{ 'sb-footer--rail': rail }">
    <!-- 1. Shop + branch -->
    <ShopBranchSwitcher v-if="!rail" class="sb-card" />

    <!-- 2. Language + notifications, side by side -->
    <div class="sb-tools">
      <div class="sb-tile"><LanguageSwitch /></div>
      <!-- <div class="sb-tile"><NotificationBell /></div> -->
    </div>

    <!-- 3. User -->
    <div class="sb-user">
      <v-avatar size="36" color="primary" class="sb-avatar">
        <v-img v-if="user?.avatar" :src="user.avatar" cover />
        <span v-else class="sb-initials">{{ initials }}</span>
      </v-avatar>

      <div v-if="!rail" class="sb-user-text">
        <span class="sb-user-name">{{ user?.name }}</span>
        <span class="sb-user-role">{{ roleName }}</span>
      </div>

      <v-btn
        icon="mdi-logout"
        variant="text"
        size="small"
        class="sb-logout"
        aria-label="Log out"
        @click="emit('logout')"
      />
    </div>
  </div>
</template>

<style scoped>
.sb-footer {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 8px;
}

/* Shared card look */
.sb-card,
.sb-tile {
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* Language + bell: two equal tiles */
.sb-tools {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.sb-tile {
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
/* Let the existing buttons fill their tile */
.sb-tile :deep(.v-btn) {
  width: 100% !important;
  height: 100% !important;
  border-radius: 12px;
}

/* User row */
.sb-user {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 4px 2px;
}
.sb-initials {
  font-size: 13px;
  font-weight: 700;
  color: #fff;
}
.sb-user-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  line-height: 1.25;
}
.sb-user-name {
  font-size: 14px;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.sb-user-role {
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), 0.6);
}
.sb-logout {
  opacity: 0.7;
}

/* Rail: stack everything in one centered column */
.sb-footer--rail .sb-tools {
  grid-template-columns: 1fr;
}
.sb-footer--rail .sb-user {
  flex-direction: column;
  padding: 4px 0;
}
</style>