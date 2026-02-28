<template>
  <div class="grid grid-cols-12 gap-8 mt-6 mb-8">
    <BaseCustomerSelectPopup
      v-model="invoiceStore.newInvoice.customer"
      :valid="v.customer_id"
      :content-loading="isLoading"
      type="invoice"
      class="col-span-12 lg:col-span-5 pr-0"
    />

    <BaseInputGrid class="col-span-12 lg:col-span-7">
      <BaseInputGroup
        :label="$t('invoices.invoice_date')"
        :content-loading="isLoading"
        required
        :error="v.invoice_date.$error && v.invoice_date.$errors[0].$message"
      >
        <BaseDatePicker
          v-model="invoiceStore.newInvoice.invoice_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
          :enableTime="enableTime"
          :time24hr="time24h"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('invoices.due_date')"
        :content-loading="isLoading"
      >
        <BaseDatePicker
          v-model="invoiceStore.newInvoice.due_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('invoices.invoice_number')"
        :content-loading="isLoading"
        :error="v.invoice_number.$error && v.invoice_number.$errors[0].$message"
        required
      >
        <BaseInput
          v-model="invoiceStore.newInvoice.invoice_number"
          :content-loading="isLoading"
          @input="v.invoice_number.$touch()"
        />
      </BaseInputGroup>

      <ExchangeRateConverter
        :store="invoiceStore"
        store-prop="newInvoice"
        :v="v"
        :is-loading="isLoading"
        :is-edit="isEdit"
        :customer-currency="invoiceStore.newInvoice.currency_id"
      />

      <BaseInputGroup
        v-if="bankAccountOptions.length > 0"
        :label="$t('invoices.payment_qr_code')"
        :content-loading="isLoading"
      >
        <BaseMultiselect
          v-model="invoiceStore.newInvoice.bank_account_id"
          :options="bankAccountOptions"
          value-prop="id"
          label="label"
          track-by="label"
          :searchable="true"
          :can-deselect="true"
          :placeholder="$t('invoices.no_qr_code')"
          @update:modelValue="onBankAccountChange"
        />
      </BaseInputGroup>

      <BaseInputGroup
        v-for="field in invoicePaymentFields"
        :key="field.key"
        :label="field.label"
        :content-loading="isLoading"
        :required="field.required"
      >
        <BaseInput
          v-model="invoiceStore.newInvoice.payment_details[field.key]"
          type="text"
          :placeholder="field.placeholder || ''"
        />
      </BaseInputGroup>
    </BaseInputGrid>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import ExchangeRateConverter from '@/scripts/admin/components/estimate-invoice-common/ExchangeRateConverter.vue'
import { useInvoiceStore } from '@/scripts/admin/stores/invoice'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useBankAccountStore } from '@/scripts/admin/stores/bank-account'

const props = defineProps({
  v: {
    type: Object,
    default: null,
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
  isEdit: {
    type: Boolean,
    default: false,
  },
})

const invoiceStore = useInvoiceStore()
const companyStore = useCompanyStore()
const bankAccountStore = useBankAccountStore()

const enableTime = computed(() => {
  return (
    companyStore.selectedCompanySettings.invoice_use_time === 'YES'
  );
})
const time24h = computed(() => {
  return (
    companyStore.selectedCompanySettings.carbon_time_format.indexOf('H') > -1
  );
})

const invoiceCurrencyCode = computed(() => {
  return invoiceStore.newInvoice.selectedCurrency?.code ?? null
})

function isBankAccountCompatible(ba) {
  const qrType = bankAccountStore.qrTypes.find((qt) => qt.type === ba.qr_code_type)
  if (!qrType || !qrType.supported_currencies) return true
  if (!invoiceCurrencyCode.value) return true
  return qrType.supported_currencies.includes(invoiceCurrencyCode.value)
}

const bankAccountOptions = computed(() => {
  return bankAccountStore.bankAccounts
    .filter(isBankAccountCompatible)
    .map((ba) => {
      const currencyCode = ba.currency ? ba.currency.code : ''
      const displayLabel = ba.display_label || ''
      let label = ba.name
      if (displayLabel) {
        label += ' - ' + displayLabel
      }
      if (currencyCode) {
        label += ' (' + currencyCode + ')'
      }
      return { id: ba.id, label }
    })
})

const selectedBankAccount = computed(() => {
  if (!invoiceStore.newInvoice.bank_account_id) return null
  return bankAccountStore.bankAccounts.find(
    (ba) => ba.id === invoiceStore.newInvoice.bank_account_id
  )
})

const invoicePaymentFields = computed(() => {
  if (!selectedBankAccount.value) return []
  const qrType = bankAccountStore.qrTypes.find(
    (qt) => qt.type === selectedBankAccount.value.qr_code_type
  )
  return qrType?.invoice_fields ?? []
})

function onBankAccountChange() {
  invoiceStore.newInvoice.payment_details = {}
}

</script>
