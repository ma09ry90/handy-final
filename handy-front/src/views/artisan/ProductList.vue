<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '@/plugins/axios';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

const router = useRouter();
const { t } = useI18n();

const products = ref([]);
const loading = ref(true);

// --- Lightbox State ---
const isLightboxOpen = ref(false);
const selectedProduct = ref(null);
const currentImageIndex = ref(0);

// --- Image URL Helper ---
const getImageUrl = (path) => {
    if (!path) return 'https://via.placeholder.com/400x300?text=No+Image';
    if (path.startsWith('http')) return path; // Cloudinary URL

    const baseUrl = import.meta.env.VITE_API_BASE_URL || '';
    const cleanBase = baseUrl.replace(/\/api$/, '');
    return `${cleanBase}/${path}`;
};

onMounted(async () => {
  await fetchProducts();
});

const fetchProducts = async () => {
  try {
    loading.value = true;
    const { data } = await api.get('/artisan/products');
    products.value = data;
  } catch (error) {
    console.error(error);
    alert(t('common.error_loading'));
  } finally {
    loading.value = false;
  }
};

const deleteProduct = async (id) => {
  if (!confirm(t('common.confirm_delete'))) return;
  try {
    await api.delete(`/artisan/products/${id}`);
    alert(t('common.success_delete'));
    await fetchProducts();
  } catch (error) {
    console.error(error);
    alert(t('common.error_occurred'));
  }
};

// --- DISPLAY HELPERS ---

// REMOVED: The complex 'getProductName' and 'getProductDescription' functions.
// The Controller now handles language fallback and sends 'name'/'description' directly.

const getShortDescription = (product) => {
    // Use description directly from backend response
    const fullDesc = product.description; 
    if (!fullDesc) return t('product.no_description');
    if (fullDesc.length > 60) return fullDesc.substring(0, 60) + '...';
    return fullDesc;
};

const getProductImage = (product) => {
  // Controller returns: images: [{ id: 1, image_path: '...' }]
  if (product.images && product.images.length > 0) {
    return getImageUrl(product.images[0].image_path);
  }
  return 'https://via.placeholder.com/400x300?text=No+Image';
};

const formatPrice = (price) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'ETB' }).format(price);
};

// --- Lightbox Controls ---

const openLightbox = (product) => {
  selectedProduct.value = product;
  currentImageIndex.value = 0;
  isLightboxOpen.value = true;
};

const closeLightbox = () => {
  isLightboxOpen.value = false;
  selectedProduct.value = null;
};

const nextImage = () => {
  if (!selectedProduct.value) return;
  const images = selectedProduct.value.images;
  if (currentImageIndex.value < images.length - 1) {
    currentImageIndex.value++;
  } else {
    currentImageIndex.value = 0;
  }
};

const prevImage = () => {
  if (!selectedProduct.value) return;
  const images = selectedProduct.value.images;
  if (currentImageIndex.value > 0) {
    currentImageIndex.value--;
  } else {
    currentImageIndex.value = images.length - 1;
  }
};

const currentLightboxImage = computed(() => {
  if (!selectedProduct.value || !selectedProduct.value.images) return '';
  
  const images = selectedProduct.value.images;
  const currentImg = images[currentImageIndex.value];
  
  // Handle object format: { image_path: '...' }
  const path = currentImg.image_path;
  return getImageUrl(path);
});

</script>
<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <h1 class="text-3xl font-bold text-gray-900">{{ $t('product.my_products') }}</h1>
        <router-link 
          to="/artisan/products/create" 
          class="bg-emerald-600 text-white px-4 py-2 rounded-md hover:bg-emerald-700 transition"
        >
          + {{ $t('product.add_new') }}
        </router-link>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      
      <div v-if="loading" class="text-center py-20 text-gray-500">{{ $t('common.loading') }}</div>

      <!-- Empty State -->
      <div v-else-if="products.length === 0" class="text-center py-20 bg-white rounded-lg shadow">
        <h3 class="mt-2 text-sm font-medium text-gray-900">{{ $t('product.no_products') }}</h3>
        <p class="mt-1 text-sm text-gray-500">{{ $t('product.start_creating') }}</p>
        <div class="mt-6">
          <router-link to="/artisan/products/create" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-emerald-600 hover:bg-emerald-700">
            + {{ $t('product.add_product') }}
          </router-link>
        </div>
      </div>

      <!-- Product Grid -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        
        <!-- Product Card -->
        <div 
          v-for="product in products" 
          :key="product.id" 
          class="bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow duration-200 flex flex-col"
        >
          <!-- Image Container -->
          <div 
            class="relative aspect-square bg-gray-200 cursor-pointer group"
            @click="openLightbox(product)"
          >
            <img 
              :src="getProductImage(product)" 
              :alt="product.name"
              class="w-full h-full object-cover"
            />
            
            <!-- Hover Overlay -->
            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all flex items-center justify-center">
                <span class="text-white opacity-0 group-hover:opacity-100 transition-opacity font-medium text-sm bg-black bg-opacity-50 px-3 py-1 rounded">
                    {{ $t('product.view_details') }}
                </span>
            </div>

            <!-- Image Count Badge -->
            <span 
              v-if="product.images && product.images.length > 1"
              class="absolute top-2 right-2 bg-black bg-opacity-70 text-white text-xs px-2 py-1 rounded"
            >
              {{ product.images.length }} {{ $t('product.photos') }}
            </span>
            
            <!-- Stock Badge -->
            <span 
              class="absolute top-2 left-2 px-2 py-1 text-xs font-bold rounded"
              :class="{
                'bg-red-100 text-red-800': product.stock === 0,
                'bg-orange-100 text-orange-800': product.stock > 0 && product.stock <= 5,
                'bg-green-100 text-green-800': product.stock > 5
              }"
            >
              {{ product.stock === 0 ? $t('product.out_of_stock') : product.stock + ' ' + $t('product.in_stock') }}
            </span>
          </div>

          <!-- Card Content -->
          <div class="p-4 flex-1 flex flex-col">
            <!-- Name (FIXED: Use direct property) -->
            <h3 class="text-sm font-medium text-gray-900 mb-1 truncate" :title="product.name">
              {{ product.name }}
            </h3>
            
            <!-- Description Preview -->
            <p class="text-xs text-gray-500 mb-2 line-clamp-2">
                {{ getShortDescription(product) }}
            </p>
            <!-- Price (FIXED: Use direct property) -->
            <p class="text-lg font-bold text-emerald-600 mb-4 mt-auto">
              {{ formatPrice(product.price ?? 0) }}
            </p>

            <!-- Actions -->
            <div class="flex items-center justify-between border-t pt-3">
              <button 
                @click="router.push('/artisan/products/' + product.id + '/edit')" 
                class="text-sm text-indigo-600 hover:text-indigo-900 font-medium"
              >
                {{ $t('common.edit') }}
              </button>
              <button 
                @click="deleteProduct(product.id)" 
                class="text-sm text-red-600 hover:text-red-900 font-medium"
              >
                {{ $t('common.delete') }}
              </button>
            </div>
          </div>
        </div>

      </div>
    </main>

    <!-- LIGHTBOX MODAL -->
    <div 
      v-if="isLightboxOpen" 
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-90 p-4"
      @click.self="closeLightbox"
    >
        <!-- Close Button -->
        <button 
            @click="closeLightbox" 
            class="absolute top-4 right-4 text-white text-4xl hover:text-gray-300 z-50"
        >
            &times;
        </button>

        <!-- Main Content Area -->
        <div class="flex flex-col md:flex-row gap-6 max-w-5xl w-full max-h-[90vh] overflow-auto bg-white rounded-lg shadow-2xl">
            
            <!-- Left: Image Section -->
            <div class="relative w-full md:w-2/3 bg-gray-100 flex-shrink-0">
                <!-- Image -->
                <img 
                    :src="currentLightboxImage" 
                    class="w-full h-auto max-h-[60vh] object-contain mx-auto"
                />

                <!-- Left Arrow -->
                <button 
                    v-if="selectedProduct?.images?.length > 1"
                    @click="prevImage" 
                    class="absolute left-2 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-80 hover:bg-opacity-100 text-gray-800 p-2 rounded-full shadow"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>

                <!-- Right Arrow -->
                <button 
                    v-if="selectedProduct?.images?.length > 1"
                    @click="nextImage" 
                    class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-white bg-opacity-80 hover:bg-opacity-100 text-gray-800 p-2 rounded-full shadow"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>

            <!-- Right: Details Section -->
            <div class="w-full md:w-1/3 p-6 flex flex-col">
                <!-- Name (FIXED) -->
                <h2 class="text-2xl font-bold text-gray-900 mb-2">
                    {{ selectedProduct?.name }}
                </h2>
                
                <!-- Price (FIXED: Use direct property) -->
                <p class="text-2xl font-bold text-emerald-600 mb-4">
                    {{ formatPrice(selectedProduct?.price ?? 0) }}
                </p>
                <div class="border-t pt-4 mb-4">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">{{ $t('product.description') }}</h4>
                    <p class="text-sm text-gray-700 leading-relaxed overflow-y-auto max-h-40">
                        {{ selectedProduct?.description }}
                    </p>
                </div>
                <div class="mt-auto border-t pt-4">
                    <!-- Stock (FIXED: Use direct property) -->
                    <p class="text-xs text-gray-500">
                        {{ $t('product.stock_units', { count: selectedProduct?.stock ?? 0 }) }}
                    </p>
                    <!-- Thumbnails -->
                     <div class="flex gap-2 mt-4">
                         <div 
                            v-for="(img, index) in selectedProduct?.images" 
                            :key="img.id" 
                            @click="currentImageIndex = index"
                            class="w-16 h-16 border-2 rounded cursor-pointer flex-shrink-0"
                            :class="index === currentImageIndex ? 'border-emerald-500' : 'border-transparent opacity-60 hover:opacity-100'"
                        >
                            <img :src="getImageUrl(img.image_path)" class="w-full h-full object-cover">
                        </div>
                     </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END LIGHTBOX -->

  </div>
</template>