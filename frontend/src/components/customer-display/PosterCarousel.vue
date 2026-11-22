<template>
  <div class="cd-poster">
    <transition name="cd-poster-fade">
      <img
        v-if="posters.length"
        :key="currentIndex"
        :src="posters[currentIndex]"
        class="cd-poster__image"
        alt=""
      />
      <div v-else key="placeholder" class="cd-poster__placeholder">
        <div class="cd-poster__placeholder-mark">&#10022;</div>
      </div>
    </transition>

    <!-- Ambient brand-colored glow, bottom-right — a deliberate decorative
         accent (not a bug fix) that also happily covers any stray edge
         artifact in that corner from the crossfading poster above it. -->
    <div class="cd-poster__glow" aria-hidden="true"></div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'

const ROTATE_MS = 8000

const props = defineProps({
  posters: { type: Array, default: () => [] }
})

const currentIndex = ref(0)
let timer = null

function clearTimer() {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

// Only worth rotating with 2+ posters — a single poster just sits still,
// no pointless self-crossfade.
function startRotation() {
  clearTimer()
  if (props.posters.length < 2) return
  timer = setInterval(() => {
    currentIndex.value = (currentIndex.value + 1) % props.posters.length
  }, ROTATE_MS)
}

watch(
  () => props.posters,
  posters => {
    if (currentIndex.value >= posters.length) currentIndex.value = 0
    startRotation()
  }
)

onMounted(startRotation)
onBeforeUnmount(clearTimer)
</script>

<style scoped>
.cd-poster {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
  background: #0f172a;
}

.cd-poster__image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  /* contain, not cover — a cropped poster can look like it's "running off"
     the screen on an aspect ratio the image wasn't made for; the gradient
     behind it (same one the no-poster placeholder uses) fills any
     letterboxed edges deliberately, rather than leaving a stray sliver of
     the container's own background exposed by object-fit rounding. */
  object-fit: contain;
  background: linear-gradient(160deg, #1e293b, #0f172a);
}

.cd-poster__placeholder {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(160deg, #1e293b, #0f172a);
}

.cd-poster__placeholder-mark {
  font-family: 'Space Grotesk', sans-serif;
  font-size: clamp(48px, 7vw, 96px);
  color: rgba(255, 255, 255, 0.18);
}

/* Both the outgoing and incoming image are absolutely positioned and the
   default (simultaneous) transition mode is used — a true crossfade, not
   an out-in fade-to-black-then-in. */
.cd-poster-fade-enter-active,
.cd-poster-fade-leave-active {
  transition: opacity 0.9s ease;
}

.cd-poster-fade-enter-from,
.cd-poster-fade-leave-to {
  opacity: 0;
}

.cd-poster__glow {
  position: absolute;
  right: clamp(-60px, -6vw, -30px);
  bottom: clamp(-60px, -6vw, -30px);
  width: clamp(220px, 26vw, 420px);
  aspect-ratio: 1 / 1;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(79, 70, 229, 0.55) 0%, rgba(79, 70, 229, 0.25) 45%, rgba(79, 70, 229, 0) 72%);
  filter: blur(6px);
  mix-blend-mode: screen;
  pointer-events: none;
  animation: cd-glow-pulse 6s ease-in-out infinite;
}

@keyframes cd-glow-pulse {
  0%,
  100% {
    transform: scale(0.92);
    opacity: 0.75;
  }
  50% {
    transform: scale(1.08);
    opacity: 1;
  }
}

@media (prefers-reduced-motion: reduce) {
  .cd-poster__glow {
    animation: none;
  }
}
</style>
