<template>
  <Teleport to="body">
    <v-navigation-drawer
      :model-value="modelValue"
      location="right"
      temporary
      :width="width"
      :persistent="persistent"
      class="app-drawer"
      @update:model-value="handleUpdateModelValue"
    >
      <div class="d-flex flex-column h-100">
        <div class="app-drawer__header d-flex align-start pa-5 pb-4 flex-shrink-0">
          <div class="min-width-0">
            <div class="text-subtitle-1 font-weight-bold">
              <slot name="title" />
            </div>
            <div v-if="$slots.subtitle" class="text-caption text-medium-emphasis mt-1">
              <slot name="subtitle" />
            </div>
          </div>
          <v-spacer />
          <v-btn icon="mdi-close" variant="text" size="small" @click="requestClose" />
        </div>

        <div class="app-drawer__body flex-grow-1 px-5 pb-5">
          <slot />
        </div>
      </div>

      <template v-if="$slots.footer" #append>
        <div class="app-drawer__footer pa-4 d-flex ga-2 bg-surface flex-shrink-0">
          <slot name="footer" />
        </div>
      </template>
    </v-navigation-drawer>
  </Teleport>
</template>

<script setup>
  // Shared shell for every right-side editor drawer in the app (option
  // groups, categories, branches, staff, shifts, ...). Rendered through
  // <Teleport to="body"> rather than relying on being a page-root sibling —
  // several existing drawers already WERE page-root siblings (outside any
  // v-window/tabs) and still only dimmed their local tab content instead of
  // the whole page, so "move it to the root" alone isn't a reliable fix in
  // this app; teleporting guarantees the scrim covers everything regardless
  // of whatever ancestor is doing it (unconfirmed — never pinned down which
  // CSS property was responsible, but teleporting sidesteps the question
  // entirely).
  const props = defineProps({
    modelValue: { type: Boolean, default: false },
    width: { type: [Number, String], default: 460 },
    // While true (e.g. a save in flight), the drawer can't be dismissed at
    // all — same semantics as Vuetify's own `persistent` prop, just named
    // to match how every existing drawer already used a `loading` prop to
    // guard its own close() function.
    persistent: { type: Boolean, default: false },
    // Optional — () => boolean | Promise<boolean>. Called on every close
    // attempt (X button, scrim click, Esc — all three funnel through the
    // same v-model update) when not persistent. Returning false cancels the
    // close. Lets a consumer with a dirty form show "Discard changes?"
    // before actually closing, without AppDrawer needing to know anything
    // about that consumer's own form shape.
    confirmClose: { type: Function, default: null }
  })

  const emit = defineEmits(['update:modelValue'])

  async function requestClose() {
    if (props.persistent) return
    if (props.confirmClose) {
      const ok = await props.confirmClose()
      if (!ok) return
    }
    emit('update:modelValue', false)
  }

  function handleUpdateModelValue(value) {
    if (value) {
      emit('update:modelValue', true)
      return
    }
    // Vuetify's own v-overlay already closes on scrim-click/Esc unless
    // `persistent` is set — intercepting here routes that same attempt
    // through the confirm-before-close gate instead of letting it close
    // unconditionally.
    requestClose()
  }
</script>

<style scoped>
  .min-width-0 {
    min-width: 0;
  }
  .app-drawer__body {
    overflow-y: auto;
    min-height: 0;
    -webkit-overflow-scrolling: touch;
  }
</style>
