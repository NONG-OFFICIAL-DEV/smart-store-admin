<template>
  <v-app-bar scroll-behavior="inverted" elevation="0" border flat>
    <!-- Nav toggle -->
    <v-app-bar-nav-icon
      @click="emit('toggle')"
      :color="rail ? 'primary' : undefined"
      icon="mdi-menu"
    ></v-app-bar-nav-icon>
    <v-divider class="mx-3" inset vertical />

    <!-- The platform logo lives in the Sidebar header for every layout (see
         Sidebar.vue) — never duplicated here. -->
    <ShopBranchSwitcher />

    <template v-slot:append>
      <AppBarActions :user="user" :bu-name="buName" :branch-name="branchName" :role-name="roleName" />
    </template>
  </v-app-bar>
</template>

<script setup>
  import ShopBranchSwitcher from './ShopBranchSwitcher.vue'
  import AppBarActions from './AppBarActions.vue'

  const emit = defineEmits(['toggle'])

  defineProps({
    rail: Boolean,
    user: Object,
    buName: String,
    branchName: String,
    roleName: String
  })
</script>
