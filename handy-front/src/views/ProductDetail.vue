<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/plugins/axios';
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'

const route = useRoute();
const router = useRouter();
const product = ref(null);
const loading = ref(true);

// --- Media URL Helper ---
const getImageUrl = (path) => {
    if (!path) return 'https://via.placeholder.com/400';
    
    // If it's already a full Cloudinary/external URL, return it as-is
    if (path.startsWith('http://') || path.startsWith('https://')) return path;

    // Otherwise, construct local URL
    const baseUrl = import.meta.env.VITE_API_BASE_URL || '';
    const cleanBase = baseUrl.replace(/\/api$/, '');
    return `${cleanBase}/${path}`;
};

// --- Gallery State ---
const currentIndex = ref(0);
const isLightboxOpen = ref(false);

const currentImage = computed(() => {
    if (!product.value || !product.value.images?.length) return '';
    const rawImg = product.value.images[currentIndex.value];
    const path = rawImg?.image_path || rawImg;
    return getImageUrl(path);
});

const nextImage = () => {
    if (!product.value) return;
    currentIndex.value = (currentIndex.value < product.value.images.length - 1) ? currentIndex.value + 1 : 0;
};

const prevImage = () => {
    if (!product.value) return;
    currentIndex.value = (currentIndex.value > 0) ? currentIndex.value - 1 : product.value.images.length - 1;
};

const selectImage = (index) => { currentIndex.value = index; };
const openLightbox = () => { isLightboxOpen.value = true; };
const closeLightbox = () => { isLightboxOpen.value = false; };

const cartStore = useCartStore()
const wishlistStore = useWishlistStore()

const quantity = ref(1)
const isWishlisted = computed(() => wishlistStore.likedIds.includes(product.value?.id))

const handleAddToCart = async () => {
  if (!product.value || !product.value.versions?.[0]) return
  try {
    await cartStore.addToCart(product.value.id, product.value.versions[0].id, quantity.value)
  } catch (error) {
    alert(error.response?.data?.message || 'Could not add to cart.');
  }
}

const handleToggleWishlist = () => {
  if (!product.value) return
  wishlistStore.toggleWishlist(product.value.id)
}

onMounted(async () => {
    try {
        const { data } = await api.get(`/products/${route.params.id}`);
        product.value = data;
    } catch (e) {
        console.error(e);
        router.push('/');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
  <div class="bg-gray-50 min-h-screen">
    
    <div v-if="loading" class="text-center py-20 text-gray-500">Loading product...</div>
    
    <div v-else-if="product" class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
      
      <div class="lg:grid lg:grid-cols-2 lg:gap-x-12">
        
        <!-- Left Column: Media -->
        <div class="lg:max-w-lg lg:self-start space-y-6">
            
            <!-- 1. IMAGE GALLERY -->
            <div class="w-full bg-white rounded-2xl overflow-hidden shadow-lg border border-gray-200 relative">
                <div class="aspect-square relative cursor-zoom-in" @click="openLightbox">
                    <img :src="currentImage" :alt="product.name" class="w-full h-full object-cover" />
                    
                    <button v-if="product.images.length > 1" @click.stop="prevImage" class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white p-2 rounded-full shadow-lg text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    </button>
                    
                    <button v-if="product.images.length > 1" @click.stop="nextImage" class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white p-2 rounded-full shadow-lg text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>

                    <div class="absolute bottom-4 left-4 bg-black/50 text-white text-xs px-2 py-1 rounded-full">
                        {{ currentIndex + 1 }} / {{ product.images.length }}
                    </div>
                </div>

                <div v-if="product.images.length > 1" class="p-4 border-t border-gray-100 flex gap-2 overflow-x-auto">
                    <div v-for="(img, i) in product.images" :key="i" @click="selectImage(i)" class="w-16 h-16 flex-shrink-0 rounded-lg overflow-hidden border-2 transition cursor-pointer" :class="i === currentIndex ? 'border-emerald-500' : 'border-transparent hover:border-gray-300'">
                        <img :src="getImageUrl(img.image_path || img)" class="w-full h-full object-cover">
                    </div>
                </div>
            </div>

            <!-- 2. AR 3D VIEWER -->
            <div v-if="product.ar_model_path" class="w-full bg-white rounded-2xl overflow-hidden shadow-lg border border-purple-200 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-gray-800 flex items-center gap-2">View in 3D / AR</h3>
                    <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded-full font-medium">Interactive</span>
                </div>
                
                <!-- ✅ MOBILE FIX: Prevent scrolling while interacting with 3D model -->
                <div class="aspect-square bg-gray-100 rounded-xl overflow-hidden touch-none">
                    <model-viewer
                        :src="getImageUrl(product.ar_model_path)"
                        :alt="product.name"
                        ar
                        ar-modes="webxr scene-viewer quick-look"
                        camera-controls
                        auto-rotate
                        shadow-intensity="1"
                        style="width: 100%; height: 100%; background-color: #f0f0f0;"
                    >
                        <button slot="ar-button" class="absolute bottom-4 right-4 bg-purple-600 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg hover:bg-purple-700 flex items-center gap-2">
                            View in AR
                        </button>
                    </model-viewer>
                </div>
            </div>
        </div>

        <!-- Right Column: Product Info -->
        <div class="mt-10 px-4 sm:px-0 sm:mt-16 lg:mt-0">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ product.name }}</h1>
            <div class="mt-3">
                <p class="text-3xl tracking-tight text-emerald-600 font-extrabold">${{ product.price }}</p>
            </div>

            <div class="mt-6">
                <span v-if="product.stock > 0" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">In Stock</span>
                <span v-else class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">Out of Stock</span>
            </div>

            <div class="mt-6 text-base text-gray-700 space-y-6" v-html="product.description"></div>

            <!-- ✅ FIX: Removed the duplicate "Sold by" section that was here -->

            <div class="mt-8 border-t border-gray-200 pt-8">
                <p class="text-sm text-gray-500 mb-4">
                    Sold by: <span class="text-gray-900 font-medium">{{ product.artisan?.shop || 'Handy Artisan' }}</span>
                </p>

                <div class="flex items-center gap-4 mb-6">
                    <span class="text-gray-700 font-medium">Quantity:</span>
                    <div class="flex items-center border rounded-lg overflow-hidden">
                        <button @click="quantity > 1 && quantity--" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800">-</button>
                        <span class="px-6 py-2 bg-white text-gray-900 font-bold">{{ quantity }}</span>
                        <button @click="quantity++" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800">+</button>
                    </div>
                </div>

                <div class="flex gap-4">
                    <button @click="handleAddToCart" :disabled="!product.stock || product.stock <= 0" class="flex-1 flex items-center justify-center rounded-md bg-emerald-600 px-8 py-4 text-lg font-medium text-white hover:bg-emerald-700 disabled:bg-gray-400 shadow-lg transition">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        {{ product.stock > 0 ? 'Add to Cart' : 'Out of Stock' }}
                    </button>

                    <button @click="handleToggleWishlist" class="p-4 rounded-md border-2 transition" :class="isWishlisted ? 'border-red-500 bg-red-50 text-red-500' : 'border-gray-200 text-gray-400 hover:border-red-300 hover:text-red-400'">
                        <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
            </div>
        </div>
      </div>
    </div>

    <!-- LIGHTBOX MODAL -->
    <div v-if="isLightboxOpen" class="fixed inset-0 z-50 bg-black bg-opacity-90 flex items-center justify-center" @click="closeLightbox">
        <button @click="closeLightbox" class="absolute top-4 right-4 text-white hover:text-gray-300 z-10">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
        <div class="relative max-w-5xl max-h-[90vh] w-full flex items-center justify-center p-12">
            <img :src="currentImage" class="max-h-full max-w-full object-contain" @click.stop>
            <button v-if="product.images.length > 1" @click.stop="prevImage" class="absolute left-4 text-white bg-black/50 p-3 rounded-full"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
            <button v-if="product.images.length > 1" @click.stop="nextImage" class="absolute right-4 text-white bg-black/50 p-3 rounded-full"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
        </div>
    </div>
  </div>
</template>