<template>
  <BaseDropdown>
    <template #activator>
      <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-gray-500" />
    </template>

    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.EDIT_BANK_ACCOUNT)"
      @click="editBankAccount"
    >
      <BaseIcon name="PencilIcon" class="h-5 mr-3 text-gray-400" />
      {{ $t('general.edit') }}
    </BaseDropdownItem>

    <BaseDropdownItem
      v-if="!row.is_default"
      @click="setAsDefault"
    >
      <BaseIcon name="StarIcon" class="h-5 mr-3 text-gray-400" />
      {{ $t('settings.bank_accounts.set_as_default') }}
    </BaseDropdownItem>

    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.DELETE_BANK_ACCOUNT)"
      @click="removeBankAccount(row.id)"
    >
      <BaseIcon name="TrashIcon" class="h-5 mr-3 text-red-400" />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup>
import { useBankAccountStore } from '@/scripts/admin/stores/bank-account'
import { useModalStore } from '@/scripts/stores/modal'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useI18n } from 'vue-i18n'
import abilities from '@/scripts/admin/stub/abilities'

const props = defineProps({
  row: { type: Object, required: true },
  table: { type: Object, default: null },
  loadData: { type: Function, default: null },
})

const bankAccountStore = useBankAccountStore()
const modalStore = useModalStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()
const { t } = useI18n()

function editBankAccount() {
  bankAccountStore.currentBankAccount = { ...props.row }
  modalStore.openModal({
    title: t('settings.bank_accounts.edit_account'),
    componentName: 'BankAccountModal',
    size: 'md',
    refreshData: props.loadData,
  })
}

async function setAsDefault() {
  const data = { ...props.row, is_default: true }
  await bankAccountStore.updateBankAccount(data)
  if (props.loadData) props.loadData()
}

async function removeBankAccount(id) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('settings.bank_accounts.confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
    })
    .then(async (response) => {
      if (response) {
        await bankAccountStore.deleteBankAccount(id)
        if (props.loadData) props.loadData()
      }
    })
}
</script>
