import { useI18n } from 'vue-i18n'
import { useAppUtils } from '@nong-official-dev/core'

// Shared "Discard unsaved changes?" gate for AppDrawer's confirmClose prop —
// confirm() is callback-based (agree/cancel), wrapped here as a Promise<bool>
// since AppDrawer awaits confirmClose() before actually closing.
export function useConfirmDiscard() {
  const { t } = useI18n()
  const { confirm } = useAppUtils()

  function confirmDiscard(isDirty) {
    if (!isDirty) return true
    return new Promise(resolve => {
      confirm({
        title: t('common.discard_changes_title'),
        message: t('common.discard_changes_message'),
        options: { type: 'warning', color: 'warning', width: 400 },
        agree: () => resolve(true),
        cancel: () => resolve(false)
      })
    })
  }

  return { confirmDiscard }
}
