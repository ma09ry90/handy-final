<template>
  <div class="min-h-screen bg-gray-50 p-4 md:p-8 font-sans">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
          {{ safeT('dashboard.welcome', 'Welcome back!') }} 👋
        </h1>
        <p class="text-gray-500 mt-1">{{ safeT('dashboard.overview', 'Here is your business overview') }}</p>
      </div>
      <router-link to="/artisan/products" 
        class="mt-4 md:mt-0 inline-flex items-center justify-center px-6 py-3 bg-indigo-600 text-white font-semibold rounded-xl shadow-md hover:bg-indigo-700 transition-colors">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        {{ safeT('dashboard.view_all_products', 'View All Products') }}
      </router-link>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div v-for="n in 3" :key="n" class="bg-white rounded-2xl shadow-sm p-6 animate-pulse">
        <div class="h-12 w-12 bg-gray-200 rounded-xl mb-4"></div>
        <div class="h-4 bg-gray-200 rounded w-1/2 mb-2"></div>
        <div class="h-8 bg-gray-200 rounded w-1/3"></div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-r-xl shadow-sm mb-8">
      <p class="font-bold">Backend Error</p>
      <p class="text-sm mt-1 font-mono bg-red-100 p-2 rounded">{{ error }}</p>
    </div>

    <!-- Main Content -->
    <div v-else>
      
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-2xl shadow-sm hover:shadow-lg transition-all duration-300 p-6 border border-gray-100 group">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">{{ safeT('dashboard.total_products', 'Total Products') }}</p>
              <h2 class="text-4xl font-extrabold text-gray-800 mt-2">{{ stats.total_products }}</h2>
            </div>
            <div class="bg-indigo-100 text-indigo-600 p-4 rounded-2xl group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm hover:shadow-lg transition-all duration-300 p-6 border border-gray-100 group">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">{{ safeT('dashboard.low_stock_alerts', 'Low Stock Alerts') }}</p>
              <h2 class="text-4xl font-extrabold mt-2" :class="stats.low_stock_alerts > 0 ? 'text-red-600' : 'text-gray-800'">{{ stats.low_stock_alerts }}</h2>
              <p v-if="stats.low_stock_alerts === 0" class="text-xs text-green-500 mt-1 font-semibold">✅ {{ safeT('dashboard.no_alerts', 'All items fully stocked') }}</p>
            </div>
            <div class="bg-red-100 text-red-600 p-4 rounded-2xl group-hover:bg-red-600 group-hover:text-white transition-colors duration-300">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm hover:shadow-lg transition-all duration-300 p-6 border border-gray-100 sm:col-span-2 lg:col-span-1 group">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">{{ safeT('dashboard.total_revenue', 'Total Revenue') }}</p>
              <h2 class="text-4xl font-extrabold text-gray-800 mt-2">${{ stats.total_revenue }}</h2>
            </div>
            <div class="bg-emerald-100 text-emerald-600 p-4 rounded-2xl group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-300">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Low Stock Warning Box -->
      <div v-if="stats.low_stock_alerts > 0" class="bg-amber-50 border border-amber-200 rounded-2xl p-6 flex items-start gap-4 mb-8">
        <div class="text-amber-500 mt-1">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        </div>
        <div>
          <h3 class="font-bold text-amber-800">{{ safeT('dashboard.attention', 'Attention Required') }}</h3>
          <p class="text-amber-700 text-sm mt-1">{{ safeT('dashboard.low_stock_message', 'You have ' + stats.low_stock_alerts + ' item(s) running low on stock.') }}</p>
        </div>
      </div>

      <!-- Recent Products Table -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
          <h3 class="text-lg font-bold text-gray-800">{{ safeT('dashboard.recent_products', 'Recent Products') }}</h3>
          <router-link to="/artisan/products" class="text-sm text-indigo-600 font-semibold hover:text-indigo-800">
            {{ safeT('dashboard.view_all', 'View All') }} →
          </router-link>
        </div>
        
        <!-- Empty State -->
        <div v-if="products.length === 0" class="p-10 text-center text-gray-400">
          <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
          <p class="font-semibold text-lg">{{ safeT('dashboard.no_products', 'No products added yet') }}</p>
        </div>
        
        <!-- Table for Desktop -->
        <table v-else class="w-full text-left hidden md:table">
          <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
            <tr>
              <th class="px-6 py-3">{{ safeT('dashboard.product_name', 'Product Name') }}</th>
              <th class="px-6 py-3">{{ safeT('dashboard.stock', 'Stock') }}</th>
              <th class="px-6 py-3">{{ safeT('dashboard.price', 'Price') }}</th>
              <th class="px-6 py-3">Features</th>
              <th class="px-6 py-3">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            
            <!-- ✅ FIX: Replaced router-link with standard <tr> -->
            <tr 
              v-for="product in products" 
              :key="product.id" 
              @click="$router.push('/artisan/products/' + product.id)"
              class="hover:bg-gray-50 transition-colors cursor-pointer"
            >
              <td class="px-6 py-4 font-medium text-gray-800 flex items-center gap-3">
                 <img v-if="product.image" :src="product.image" class="w-10 h-10 rounded-lg object-cover bg-gray-100">
                 <div v-else class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">?</div>
                 {{ product.name }}
              </td>
              <td class="px-6 py-4">
                <span class="px-3 py-1 rounded-full text-xs font-bold" 
                  :class="product.stock < 5 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'">
                  {{ product.stock }}
                </span>
              </td>
              <td class="px-6 py-4 text-gray-600 font-semibold">${{ product.price }}</td>
              <td class="px-6 py-4">
                <span v-if="product.has_ar" class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-bold rounded-md flex items-center gap-1 w-fit">AR</span>
              </td>
              <td class="px-6 py-4 text-indigo-600 font-semibold text-sm">View →</td>
            </tr>

          </tbody>
        </table>

        <!-- Cards for Mobile -->
        <div class="md:hidden divide-y divide-gray-100">
          <router-link 
            v-for="product in products" 
            :key="product.id" 
            :to="'/artisan/products/' + product.id"
            class="p-4 flex justify-between items-center block hover:bg-gray-50"
          >
            <div class="flex items-center gap-3">
              <div class="relative">
                <img v-if="product.image" :src="product.image" class="w-12 h-12 rounded-lg object-cover bg-gray-100">
                <div v-else class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">?</div>
                <span v-if="product.has_ar" class="absolute -top-1 -right-1 bg-purple-500 text-white text-[10px] px-1 rounded font-bold">AR</span>
              </div>
              <div>
                <p class="font-semibold text-gray-800">{{ product.name }}</p>
                <p class="text-xs text-gray-500">${{ product.price }} · Stock: {{ product.stock }}</p>
              </div>
            </div>
            <span class="text-indigo-600 text-sm">→</span>
          </router-link>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../plugins/axios'

const { t, te } = useI18n()

const safeT = (key, fallback) => {
  if (te(key)) return t(key)
  return fallback
}

const loading = ref(true)
const error = ref(null)
const stats = ref({ total_products: 0, low_stock_alerts: 0, total_revenue: 0 })
const products = ref([])

const fetchDashboard = async () => {
  try {
    loading.value = true
    error.value = null
    const response = await api.get('/artisan/dashboard')
    const data = response.data

    if (data.stats) {
      stats.value = data.stats
      products.value = data.recent_products || []
    } else {
      stats.value = {
        total_products: data.total_products || 0,
        low_stock_alerts: data.low_stock_alerts || 0,
        total_revenue: data.total_revenue || 0
      }
      if (data.id) {
        products.value = [{
          id: data.id,
          name: data.name || 'Product',
          stock: data.stock || 0,
          price: data.price || 0,
          image: data.image || null,
          has_ar: !!data.ar_model_path
        }]
      }
    }

  } catch (err) {
    console.error("Dashboard fetch error:", err)
    error.value = err.response?.data?.error_debug || err.response?.data?.message || err.message
  } finally {
    loading.value = false
  }
}

onMounted(() => { fetchDashboard() })
</script>