<template>
  <BaseSettingCard
    :title="$t('settings.bank_accounts.title')"
    :description="$t('settings.bank_accounts.description')"
  >
    <BankAccountModal />

    <template v-if="userStore.hasAbilities(abilities.CREATE_BANK_ACCOUNT)" #action>
      <BaseButton type="submit" variant="primary-outline" @click="openBankAccountModal">
        <template #left="slotProps">
          <BaseIcon :class="slotProps.class" name="PlusIcon" />
        </template>
        {{ $t('settings.bank_accounts.add_new_account') }}
      </BaseButton>
    </template>

    <BaseTable
      ref="table"
      class="mt-16"
      :data="fetchData"
      :columns="bankAccountColumns"
    >
      <template #cell-name="{ row }">
        <div class="flex items-center">
          {{ row.data.name }}
          <span
            v-if="row.data.is_default"
            class="ml-2 text-xs font-medium text-primary-500 bg-primary-100 px-2 py-0.5 rounded-full"
          >
            {{ $t('general.default') }}
          </span>
        </div>
      </template>
      <template #cell-qr_code_type="{ row }">
        {{ getQrTypeLabel(row.data.qr_code_type) }}
      </template>
      <template #cell-currency="{ row }">
        {{ row.data.currency ? row.data.currency.code : '-' }}
      </template>
      <template #cell-actions="{ row }">
        <BankAccountDropdown
          :row="row.data"
          :table="table"
          :load-data="refreshTable"
        />
      </template>
    </BaseTable>
  </BaseSettingCard>
</template>

<script setup>
import { useBankAccountStore } from '@/scripts/admin/stores/bank-account'
import { useModalStore } from '@/scripts/stores/modal'
import { useUserStore } from '@/scripts/admin/stores/user'
import { computed, ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import abilities from '@/scripts/admin/stub/abilities'
import BankAccountModal from '@/scripts/admin/components/modal-components/BankAccountModal.vue'
import BankAccountDropdown from '@/scripts/admin/components/dropdowns/BankAccountIndexDropdown.vue'

const { t } = useI18n()
const bankAccountStore = useBankAccountStore()
const modalStore = useModalStore()
const userStore = useUserStore()
const table = ref(null)

const qrTypeLabels = ref({})

onMounted(async () => {
  const response = await bankAccountStore.fetchQrTypes()
  if (response?.data?.data) {
    response.data.data.forEach((type) => {
      qrTypeLabels.value[type.type] = type.label
    })
  }
})

const bankAccountColumns = computed(() => {
  return [
    {
      key: 'name',
      label: t('settings.bank_accounts.account_name'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
    },
    {
      key: 'account_holder_name',
      label: t('settings.bank_accounts.account_holder'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
    },
    {
      key: 'qr_code_type',
      label: t('settings.bank_accounts.qr_type'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
    },
    {
      key: 'currency',
      label: t('settings.bank_accounts.currency'),
      thClass: 'extra',
      tdClass: 'font-medium text-gray-900',
    },
    {
      key: 'actions',
      label: '',
      tdClass: 'text-right text-sm font-medium',
      sortable: false,
    },
  ]
})

function getQrTypeLabel(type) {
  return qrTypeLabels.value[type] || type
}

async function fetchData({ page, filter, sort }) {
  let data = {
    orderByField: sort.fieldName || 'created_at',
    orderBy: sort.order || 'desc',
    page,
    limit: 'all',
  }

  let response = await bankAccountStore.fetchBankAccounts(data)

  return {
    data: response.data.data,
    pagination: {
      totalPages: response.data.meta?.last_page || 1,
      currentPage: page,
      totalCount: response.data.meta?.total || response.data.data.length,
      limit: 10,
    },
  }
}

async function refreshTable() {
  table.value && table.value.refresh()
}

function openBankAccountModal() {
  modalStore.openModal({
    title: t('settings.bank_accounts.add_account'),
    componentName: 'BankAccountModal',
    size: 'md',
    refreshData: table.value && table.value.refresh,
  })
}
</script>
