<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'

const wallet = ref({ total_earnings: 0, transactions: [] })
const loading = ref(true)

const fetchWallet = async () => {
  try {
    const res = await api.get('/delivery/wallet')
    wallet.value = res.data
  } catch (e) {
    console.error("Failed to fetch wallet", e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchWallet)
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 py-10 min-h-screen bg-gray-50">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">My Wallet</h1>

    <div v-if="loading" class="text-center py-20 text-gray-500">Loading...</div>

    <div v-else>
      <!-- Total Earnings Card -->
      <div class="bg-gradient-to-r from-indigo-600 to-blue-600 text-white p-8 rounded-2xl shadow-lg mb-8">
        <p class="text-indigo-100 text-sm font-medium">Total Delivery Earnings</p>
        <p class="text-4xl font-extrabold mt-2">{{ wallet.total_earnings.toLocaleString() }} ETB</p>
      </div>

      <!-- Transaction History -->
      <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-gray-50">
          <h3 class="font-bold text-gray-700">Delivery Fee History</h3>
        </div>
        
        <div v-if="wallet.transactions.length === 0" class="p-10 text-center text-gray-400">
          No completed deliveries yet.
        </div>

        <ul v-else class="divide-y">
          <li v-for="tx in wallet.transactions" :key="tx.id" class="flex justify-between items-center px-6 py-4 hover:bg-gray-50">
            <div>
              <p class="font-mono text-sm font-bold text-gray-800">{{ tx.order_number }}</p>
              <p class="text-xs text-gray-400 mt-1">{{ tx.date }}</p>
            </div>
            <span class="text-lg font-extrabold text-emerald-600">+{{ tx.amount.toLocaleString() }} ETB</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>