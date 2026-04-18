<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/plugins/axios'

const { t } = useI18n()

const loading = ref(true)
const balance = ref(0)
const pendingBalance = ref(0)

const showWithdrawModal = ref(false)
const isWithdrawing = ref(false)
// ✅ Added bank fields to match Laravel validation
const withdrawForm = ref({ 
    amount: '', 
    bank_name: '', 
    bank_account_name: '', 
    bank_account_number: '' 
})
const withdrawError = ref('')
const withdrawSuccess = ref('')

const transactions = ref([])

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'ETB' }).format(amount || 0)
}

const fetchWalletData = async () => {
    try {
        const res = await api.get('/artisan/wallet')
        
        // ✅ ADD THIS LINE TO DEBUG
        console.log("WALLET API RESPONSE:", res.data)
        
        balance.value = res.data.available_balance
        pendingBalance.value = res.data.pending_balance
        transactions.value = res.data.transactions || []
    } catch (e) {
        console.error("Failed to fetch wallet", e)
    } finally {
        loading.value = false
    }
}

const openWithdraw = () => {
    withdrawForm.value = { amount: '', bank_name: '', bank_account_name: '', bank_account_number: '' }
    withdrawError.value = ''
    withdrawSuccess.value = ''
    showWithdrawModal.value = true
}

const submitWithdraw = async () => {
    isWithdrawing.value = true
    withdrawError.value = ''
    withdrawSuccess.value = ''
    
    if (!withdrawForm.value.amount || withdrawForm.value.amount < 50) {
        withdrawError.value = t('wallet.min_withdraw_note') + ' (50 ETB)'
        isWithdrawing.value = false
        return
    }

    try {
        await api.post('/artisan/wallet/withdraw', withdrawForm.value)
        withdrawSuccess.value = t('wallet.withdraw_success')
        setTimeout(() => {
            showWithdrawModal.value = false
            fetchWalletData() 
        }, 1500) 
    } catch (e) {
        withdrawError.value = e.response?.data?.message || t('wallet.withdraw_error')
    } finally {
        isWithdrawing.value = false
    }
}

onMounted(fetchWalletData)
</script>

<template>
    <div class="max-w-5xl mx-auto space-y-6">
        
        <!-- Loading State -->
        <div v-if="loading" class="text-center py-20 text-gray-400">Loading wallet...</div>

        <div v-else>
            <!-- Balance Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Available Balance -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <p class="text-sm font-medium text-gray-500 mb-1">{{ t('wallet.available_balance') }}</p>
                    <p class="text-3xl font-extrabold text-green-600">{{ formatCurrency(balance) }}</p>
                </div>

                <!-- Pending Clearance -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                    <p class="text-sm font-medium text-gray-500 mb-1">{{ t('wallet.pending_clearance') }}</p>
                    <p class="text-3xl font-extrabold text-yellow-600">{{ formatCurrency(pendingBalance) }}</p>
                </div>

                <!-- Withdraw Button Card -->
                <div class="bg-green-600 p-6 rounded-xl shadow-sm flex flex-col justify-center items-center text-white cursor-pointer hover:bg-green-700 transition"
                     @click="openWithdraw">
                    <span class="text-2xl mb-2">💳</span>
                    <p class="text-lg font-bold">{{ t('wallet.request_withdrawal') }}</p>
                </div>
            </div>

            <!-- Transaction History -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h3 class="font-bold text-gray-700">{{ t('wallet.history') }}</h3>
                </div>

                <div v-if="transactions.length === 0" class="p-10 text-center text-gray-400">
                    {{ t('wallet.no_history') }}
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3">{{ t('wallet.date') }}</th>
                                <th class="px-6 py-3">{{ t('wallet.type') }}</th>
                                <th class="px-6 py-3">{{ t('wallet.amount_header') }}</th>
                                <th class="px-6 py-3">{{ t('wallet.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="tx in transactions" :key="tx.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-gray-500">{{ tx.created_at }}</td>
                                <td class="px-6 py-4">
                                    <span :class="tx.type === 'credit' ? 'text-green-600' : 'text-red-600'" class="font-medium">
                                        {{ tx.type === 'credit' ? t('wallet.credit') : t('wallet.debit') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-semibold">{{ formatCurrency(tx.amount) }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 rounded-full text-xs font-bold"
                                          :class="{
                                              'bg-yellow-100 text-yellow-700': tx.status === 'pending',
                                              'bg-green-100 text-green-700': tx.status === 'approved',
                                              'bg-red-100 text-red-700': tx.status === 'rejected'
                                          }">
                                        {{ t(`wallet.${tx.status}`) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

                <!-- ✅ UPDATED Withdraw Modal with Bank Details -->
        <div v-if="showWithdrawModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="bg-white rounded-2xl p-8 w-full max-w-md mx-4 shadow-2xl max-h-[90vh] overflow-y-auto">
                <h3 class="text-xl font-bold text-gray-900 mb-2 text-center">{{ t('wallet.request_withdrawal') }}</h3>
                <p class="text-sm text-gray-500 text-center mb-6">{{ t('wallet.available_balance') }}: <span class="font-bold text-green-600">{{ formatCurrency(balance) }}</span></p>
                
                <div v-if="withdrawSuccess" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-center text-sm font-medium">
                    {{ withdrawSuccess }}
                </div>

                <div v-if="!withdrawSuccess" class="space-y-4 mb-4">
                    <input 
                        v-model="withdrawForm.amount" 
                        type="number" 
                        min="50"
                        :placeholder="t('wallet.amount')"
                        class="w-full border-2 border-gray-200 focus:border-green-500 rounded-lg p-3 text-center text-xl font-mono outline-none"
                    />
                    <input 
                        v-model="withdrawForm.bank_name" 
                        type="text" 
                        placeholder="Bank Name (e.g. Awash, CBE)"
                        class="w-full border-2 border-gray-200 focus:border-green-500 rounded-lg p-3 outline-none"
                    />
                    <input 
                        v-model="withdrawForm.bank_account_name" 
                        type="text" 
                        placeholder="Account Holder Name"
                        class="w-full border-2 border-gray-200 focus:border-green-500 rounded-lg p-3 outline-none"
                    />
                    <input 
                        v-model="withdrawForm.bank_account_number" 
                        type="text" 
                        placeholder="Account Number"
                        class="w-full border-2 border-gray-200 focus:border-green-500 rounded-lg p-3 outline-none"
                    />
                    <p class="text-xs text-gray-400 text-center">{{ t('wallet.min_withdraw_note') }} (50 ETB)</p>
                </div>
                
                <p v-if="withdrawError" class="text-red-500 text-sm text-center mb-4">{{ withdrawError }}</p>

                <div v-if="!withdrawSuccess" class="flex gap-3">
                    <button @click="showWithdrawModal = false" class="flex-1 border border-gray-300 py-3 rounded-lg font-semibold text-gray-700">
                        {{ t('wallet.cancel') }}
                    </button>
                    <button 
                        @click="submitWithdraw" 
                        :disabled="isWithdrawing"
                        class="flex-1 bg-green-600 disabled:bg-gray-300 text-white py-3 rounded-lg font-semibold"
                    >
                        {{ isWithdrawing ? '...' : t('wallet.submit') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>