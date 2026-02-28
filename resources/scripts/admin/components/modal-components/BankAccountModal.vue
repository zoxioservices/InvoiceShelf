<template>
  <BaseModal
    :show="modalStore.active && modalStore.componentName === 'BankAccountModal'"
    @close="closeBankAccountModal"
  >
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-gray-500 cursor-pointer"
          @click="closeBankAccountModal"
        />
      </div>
    </template>
    <form action="" @submit.prevent="submitBankAccountData">
      <div class="p-4 sm:p-6">
        <BaseInputGrid layout="one-column">
          <BaseInputGroup
            :label="$t('settings.bank_accounts.account_name')"
            variant="horizontal"
            :error="v$.currentBankAccount.name.$error && v$.currentBankAccount.name.$errors[0].$message"
            required
          >
            <BaseInput
              v-model="bankAccountStore.currentBankAccount.name"
              :invalid="v$.currentBankAccount.name.$error"
              type="text"
              :placeholder="$t('settings.bank_accounts.account_name_placeholder')"
              @input="v$.currentBankAccount.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.bank_accounts.account_holder')"
            variant="horizontal"
            :error="v$.currentBankAccount.account_holder_name.$error && v$.currentBankAccount.account_holder_name.$errors[0].$message"
            required
          >
            <BaseInput
              v-model="bankAccountStore.currentBankAccount.account_holder_name"
              :invalid="v$.currentBankAccount.account_holder_name.$error"
              type="text"
              @input="v$.currentBankAccount.account_holder_name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.bank_accounts.qr_type')"
            variant="horizontal"
            required
          >
            <BaseMultiselect
              v-model="bankAccountStore.currentBankAccount.qr_code_type"
              :options="availableQrTypes"
              :can-clear="false"
              value-prop="type"
              label="label"
              track-by="label"
              :searchable="false"
              @change="onQrTypeChange"
            >
              <template #option="{ option }">
                <div class="flex items-center">
                  <span :class="{ 'text-gray-400': !option.available }">
                    {{ option.label }}
                  </span>
                  <span
                    v-if="!option.available"
                    class="ml-2 text-xs text-red-500"
                  >
                    ({{ $t('general.unavailable') }})
                  </span>
                </div>
              </template>
            </BaseMultiselect>
            <p
              v-if="selectedTypeUnavailableReason"
              class="mt-1 text-xs text-red-500"
            >
              {{ selectedTypeUnavailableReason }}
            </p>
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.bank_accounts.currency')"
            variant="horizontal"
          >
            <BaseMultiselect
              v-model="bankAccountStore.currentBankAccount.currency_id"
              :options="globalStore.currencies"
              value-prop="id"
              label="name"
              track-by="name"
              :searchable="true"
              :can-clear="true"
              :placeholder="$t('settings.bank_accounts.select_currency')"
            />
          </BaseInputGroup>

          <template v-if="currentFields.length > 0">
            <BaseInputGroup
              v-for="field in currentFields"
              :key="field.key"
              :label="field.label"
              variant="horizontal"
              :required="field.required"
            >
              <BaseInput
                v-model="bankAccountStore.currentBankAccount.details[field.key]"
                type="text"
                :placeholder="field.placeholder || ''"
              />
            </BaseInputGroup>
          </template>

          <BaseInputGroup variant="horizontal">
            <div class="flex items-center">
              <input
                id="is_default"
                v-model="bankAccountStore.currentBankAccount.is_default"
                type="checkbox"
                class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
              />
              <label
                for="is_default"
                class="ml-2 text-sm text-gray-700"
              >
                {{ $t('settings.bank_accounts.set_as_default') }}
              </label>
            </div>
          </BaseInputGroup>
        </BaseInputGrid>
      </div>
      <div class="z-0 flex justify-end p-4 border-t border-solid border--200 border-modal-bg">
        <BaseButton
          class="mr-3 text-sm"
          variant="primary-outline"
          type="button"
          @click="closeBankAccountModal"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isSaving"
          :disabled="isSaving"
          variant="primary"
          type="submit"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              name="ArrowDownOnSquareIcon"
              :class="slotProps.class"
            />
          </template>
          {{ bankAccountStore.isEdit ? $t('general.update') : $t('general.save') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup>
import { useBankAccountStore } from '@/scripts/admin/stores/bank-account'
import { useModalStore } from '@/scripts/stores/modal'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { computed, ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'

const bankAccountStore = useBankAccountStore()
const modalStore = useModalStore()
const globalStore = useGlobalStore()
const { t } = useI18n()
let isSaving = ref(false)

onMounted(async () => {
  globalStore.fetchCurrencies()

  if (!bankAccountStore.qrTypes.length) {
    await bankAccountStore.fetchQrTypes()
  }
  if (
    !bankAccountStore.currentBankAccount.qr_code_type &&
    bankAccountStore.qrTypes.length
  ) {
    const firstAvailable = bankAccountStore.qrTypes.find((qt) => qt.available)
    if (firstAvailable) {
      bankAccountStore.currentBankAccount.qr_code_type = firstAvailable.type
    }
  }
})

const availableQrTypes = computed(() => {
  return bankAccountStore.qrTypes.map((qt) => ({
    ...qt,
    $isDisabled: !qt.available,
  }))
})

const selectedQrType = computed(() => {
  return bankAccountStore.qrTypes.find(
    (qt) => qt.type === bankAccountStore.currentBankAccount.qr_code_type
  )
})

const selectedTypeUnavailableReason = computed(() => {
  if (selectedQrType.value && !selectedQrType.value.available) {
    return selectedQrType.value.unavailable_reason
  }
  return null
})

const currentFields = computed(() => {
  if (selectedQrType.value) {
    return selectedQrType.value.fields || []
  }
  return []
})

function onQrTypeChange() {
  bankAccountStore.currentBankAccount.details = {}
}

const rules = computed(() => {
  return {
    currentBankAccount: {
      name: {
        required: helpers.withMessage(t('validation.required'), required),
      },
      account_holder_name: {
        required: helpers.withMessage(t('validation.required'), required),
      },
    },
  }
})

const v$ = useVuelidate(
  rules,
  computed(() => bankAccountStore)
)

async function submitBankAccountData() {
  v$.value.currentBankAccount.$touch()
  if (v$.value.currentBankAccount.$invalid) {
    return true
  }
  try {
    const action = bankAccountStore.isEdit
      ? bankAccountStore.updateBankAccount
      : bankAccountStore.addBankAccount
    isSaving.value = true
    let res = await action(bankAccountStore.currentBankAccount)
    isSaving.value = false
    modalStore.refreshData ? modalStore.refreshData(res.data.data) : ''
    closeBankAccountModal()
  } catch (err) {
    isSaving.value = false
    return true
  }
}

function closeBankAccountModal() {
  modalStore.closeModal()
  setTimeout(() => {
    bankAccountStore.resetCurrentBankAccount()
    v$.value.$reset()
  }, 300)
}
</script>
