<script setup>
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useWishlistStore } from '@/stores/wishlist'
import { useCartStore } from '@/stores/cart'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const wishlistStore = useWishlistStore()
const cartStore = useCartStore()

// --- FIX: Image URL Helper ---
// This handles converting relative paths to full URLs and strips /api automatically
const getImageUrl = (path) => {
    if (!path) return 'https://via.placeholder.com/300';
    
    // 1. If it's already a full URL (http...), return it as-is
    if (path.startsWith('http')) return path;

    // 2. Construct URL
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '';
    
    // Strip '/api' from the end if present, then add path
    const cleanBase = baseUrl.replace(/\/api$/, '');
    return `${cleanBase}/${path}`;
};

onMounted(() => {
  wishlistStore.fetchWishlist()
})

const moveToCart = async (product) => {
  if (product.versions?.[0]) {
    await cartStore.addToCart(product.id, product.versions[0].id, 1)
    await wishlistStore.toggleWishlist(product.id) // Remove from wishlist after moving
  }
}
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 py-10 min-h-screen">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">{{ t('wishlist.title') }}</h1>
    
    <div v-if="wishlistStore.items.length === 0" class="text-center py-20">
      <svg class="w-24 h-24 mx-auto text-gray-300 mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
      <p class="text-xl text-gray-500 mb-4">{{ t('wishlist.empty') }}</p>
      <RouterLink to="/" class="text-emerald-600 font-semibold hover:underline">Explore Products</RouterLink>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
      <div v-for="item in wishlistStore.items" :key="item.id" class="bg-white rounded-xl shadow-sm border overflow-hidden group flex flex-col">
        <RouterLink :to="`/product/${item.id}`" class="relative aspect-square overflow-hidden bg-gray-100">
          <!-- Pass the raw path through the helper -->
          <img :src="getImageUrl(item.images?.[0]?.image_path)" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
          <button @click.prevent="wishlistStore.toggleWishlist(item.id)" class="absolute top-3 right-3 bg-white p-2 rounded-full shadow-md text-red-500 hover:bg-red-50">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/></svg>
          </button>
        </RouterLink>
        
        <div class="p-4 mt-auto flex flex-col gap-3">
          <div>
            <h3 class="font-semibold text-gray-800 truncate">{{ item.versions?.[0]?.translations?.[0]?.name || item.slug }}</h3>
            <p class="text-emerald-600 font-bold mt-1">${{ item.versions?.[0]?.price || '0.00' }}</p>
          </div>
          
          <button @click="moveToCart(item)" class="w-full bg-gray-100 hover:bg-emerald-600 hover:text-white text-gray-800 font-semibold py-2 rounded-lg transition text-sm">
            Move to Cart
          </button>
        </div>
      </div>
    </div>
  </div>
</template>