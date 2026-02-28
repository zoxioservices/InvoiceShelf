import http from '@/scripts/http'
import { defineStore } from 'pinia'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'

export const useBankAccountStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc('bankAccount', {
    state: () => ({
      bankAccounts: [],
      qrTypes: [],
      currentBankAccount: {
        id: null,
        name: '',
        account_holder_name: '',
        currency_id: null,
        qr_code_type: '',
        details: {},
        is_default: false,
      },
    }),

    getters: {
      isEdit: (state) => (state.currentBankAccount.id ? true : false),
      defaultBankAccount: (state) =>
        state.bankAccounts.find((ba) => ba.is_default) || null,
    },

    actions: {
      resetCurrentBankAccount() {
        this.currentBankAccount = {
          id: null,
          name: '',
          account_holder_name: '',
          currency_id: null,
          qr_code_type: '',
          details: {},
          is_default: false,
        }
      },

      fetchBankAccounts(params) {
        return new Promise((resolve, reject) => {
          http
            .get('/api/v1/bank-accounts', { params })
            .then((response) => {
              this.bankAccounts = response.data.data
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchBankAccount(id) {
        return new Promise((resolve, reject) => {
          http
            .get(`/api/v1/bank-accounts/${id}`)
            .then((response) => {
              this.currentBankAccount = response.data.data
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      addBankAccount(data) {
        const notificationStore = useNotificationStore()
        return new Promise((resolve, reject) => {
          http
            .post('/api/v1/bank-accounts', data)
            .then((response) => {
              this.bankAccounts.push(response.data.data)
              if (response.data.data.is_default) {
                this.bankAccounts.forEach((ba) => {
                  if (ba.id !== response.data.data.id) {
                    ba.is_default = false
                  }
                })
              }
              notificationStore.showNotification({
                type: 'success',
                message: global.t(
                  'settings.bank_accounts.created_message'
                ),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateBankAccount(data) {
        const notificationStore = useNotificationStore()
        return new Promise((resolve, reject) => {
          http
            .put(`/api/v1/bank-accounts/${data.id}`, data)
            .then((response) => {
              let pos = this.bankAccounts.findIndex(
                (ba) => ba.id === response.data.data.id
              )
              if (pos > -1) {
                this.bankAccounts[pos] = response.data.data
              }
              if (response.data.data.is_default) {
                this.bankAccounts.forEach((ba) => {
                  if (ba.id !== response.data.data.id) {
                    ba.is_default = false
                  }
                })
              }
              notificationStore.showNotification({
                type: 'success',
                message: global.t(
                  'settings.bank_accounts.updated_message'
                ),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteBankAccount(id) {
        return new Promise((resolve, reject) => {
          http
            .post('/api/v1/bank-accounts/delete', { ids: [id] })
            .then((response) => {
              let index = this.bankAccounts.findIndex(
                (ba) => ba.id === id
              )
              if (index > -1) {
                this.bankAccounts.splice(index, 1)
              }
              const notificationStore = useNotificationStore()
              notificationStore.showNotification({
                type: 'success',
                message: global.t(
                  'settings.bank_accounts.deleted_message'
                ),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchQrTypes() {
        return new Promise((resolve, reject) => {
          http
            .get('/api/v1/payment-qr-types')
            .then((response) => {
              this.qrTypes = response.data.data
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },
    },
  })()
}
