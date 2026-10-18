let audio = null

/*
 * One shared Audio instance instead of a new one per call — avoids a
 * flood of overlapping playback if several `order.new` events land in
 * quick succession. play() is wrapped since browsers reject it with an
 * unhandled rejection when autoplay policy blocks it (rare here since
 * the staff member has already interacted with the page by logging in).
 */
export function playNewOrderSound() {
  if (!audio) audio = new Audio('/sounds/restaurant-bell.mp3')
  audio.currentTime = 0
  audio.play().catch(() => {})
}
