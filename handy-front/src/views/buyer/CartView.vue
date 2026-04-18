<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'
import router from '@/router'

const { t } = useI18n()
const cartStore = useCartStore()
const authStore = useAuthStore()

const isCheckingOut = ref(false)

// --- FIX: Image URL Helper ---
// This handles converting relative paths to full URLs and strips /api automatically
const getImageUrl = (path) => {
    if (!path) return 'https://via.placeholder.com/100';
    
    // 1. If it's already a full URL (http...), return it as-is
    if (path.startsWith('http')) return path;

    // 2. Construct URL
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '';
    
    // Strip '/api' from the end if present, then add path
    const cleanBase = baseUrl.replace(/\/api$/, '');
    return `${cleanBase}/${path}`;
};

const subtotal = computed(() => {
  return cartStore.items.reduce((sum, item) => sum + (item.price * item.quantity), 0)
})

// FIX: Check if the cart has any stock validation errors
const hasCartErrors = computed(() => {
  return !!cartStore.error || cartStore.isLoading
})

const handleCheckout = () => {
  if (!authStore.isAuthenticated) {
    alert('Please log in to proceed to checkout.')
    router.push('/login')
    return
  }
  
  // FIX: Prevent checkout if the cart is in an error state
  if (hasCartErrors.value) {
    alert('Please fix the quantity errors in your cart before checking out.')
    return
  }

  isCheckingOut.value = true
  router.push('/checkout')
}

onMounted(() => {
  if (authStore.isAuthenticated) cartStore.fetchCart()
  else cartStore.loadLocal()
})
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 py-10 min-h-screen">
    <!-- FIX: Show Cart Error Alert prominently -->
    <div v-if="cartStore.error" class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg flex items-center justify-between">
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        <span class="font-medium">{{ cartStore.error }}</span>
      </div>
      <button @click="cartStore.error = null" class="text-red-500 hover:text-red-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <h1 class="text-3xl font-bold text-gray-900 mb-8">{{ t('cart.title') }}</h1>

    <div v-if="cartStore.items.length === 0" class="text-center py-20">
      <svg class="w-24 h-24 mx-auto text-gray-300 mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
      <p class="text-xl text-gray-500 mb-4">{{ t('cart.empty') }}</p>
      <RouterLink to="/" class="text-emerald-600 font-semibold hover:underline">Continue Shopping</RouterLink>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Cart Items List -->
      <div class="lg:col-span-2 space-y-6">
        <div v-for="item in cartStore.items" :key="item.version_id || item.id" class="flex gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
          
          <RouterLink :to="`/product/${item.product_id}`" class="flex-shrink-0">
            <!-- Pass the raw path through the helper -->
            <img :src="getImageUrl(item.image)" class="w-24 h-24 object-cover rounded-lg">
          </RouterLink>

          <div class="flex-grow">
            <h3 class="font-semibold text-gray-800 truncate">{{ item.name }}</h3>
            <p class="text-emerald-600 font-bold mt-1">{{ item.price }} ETB</p>
            
            <div class="flex items-center justify-between mt-3">
              <!-- Quantity Controls -->
              <div class="flex items-center border rounded-lg overflow-hidden text-gray-700">
                <button @click="cartStore.updateQuantity(item.version_id, item.quantity - 1)" class="px-3 py-1 bg-gray-50 hover:bg-gray-100 font-bold">-</button>
                <span class="px-4 py-1 bg-white font-semibold">{{ item.quantity }}</span>
                <button @click="cartStore.updateQuantity(item.version_id, item.quantity + 1)" class="px-3 py-1 bg-gray-50 hover:bg-gray-100 font-bold">+</button>
              </div>

              <div class="flex items-center gap-4">
                <p class="font-bold text-gray-900">{{ (item.price * item.quantity).toFixed(2) }}</p>
                <button @click="cartStore.removeItem(item.version_id || item.id)" class="text-red-500 hover:text-red-700">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Order Summary -->
      <div class="lg:col-span-1 bg-gray-50 p-6 rounded-xl h-fit sticky top-24 border">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Order Summary</h2>
        <div class="flex justify-between mb-2 text-gray-600">
          <span>Subtotal ({{ cartStore.count }} items)</span>
          <span>{{ subtotal.toFixed(2) }}</span>
        </div>
        <div class="flex justify-between mb-4 text-gray-600">
          <span>Shipping</span>
          <span>Calculated at checkout</span>
        </div>
        <div class="border-t pt-4 mb-6 flex justify-between text-xl font-bold text-gray-900">
          <span>Total</span>
          <span>{{ subtotal.toFixed(2) }}</span>
        </div>
        
        <!-- FIX: Disable button and change text if there are errors -->
        <button 
          @click="handleCheckout" 
          :disabled="isCheckingOut || hasCartErrors" 
          class="w-full font-bold py-4 rounded-lg shadow transition"
          :class="hasCartErrors ? 'bg-gray-400 text-gray-200 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700 text-white'"
        >
          <span v-if="hasCartErrors">Fix Quantity Errors</span>
          <span v-else-if="!authStore.isAuthenticated">Login to Checkout</span>
          <span v-else>Proceed to Checkout</span>
        </button>

        <RouterLink to="/" class="block text-center text-sm text-gray-500 mt-4 hover:underline">Continue Shopping</RouterLink>
      </div>
    </div>
  </div>
</template>