<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import api from '@/plugins/axios';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';

const { t } = useI18n(); 
const router = useRouter();
const route = useRoute();

const isEdit = computed(() => !!route.params.id);
const loading = ref(false);
const categories = ref([]);
const errors = ref({});

const imagePreviews = ref([]);
const imageUrls = ref([]); 
const videoUrl = ref(null);
const arModelUrl = ref(null);

// ✅ Added these back to prevent template crash
const isUploadingVideo = ref(false);
const isUploadingModel = ref(false);

const form = reactive({
  category_id: '',
  price: '',
  stock: '',
  sku: '',
  name_en: '', description_en: '',
  name_am: '', description_am: '',
  name_or: '', description_or: '',
});

const vrMode = ref('none'); 

const handleImageChange = (event) => {
  const files = Array.from(event.target.files);
  files.forEach(file => {
    if (imagePreviews.value.length >= 5) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      imagePreviews.value.push(e.target.result);
      imageUrls.value.push(e.target.result); 
    };
    reader.readAsDataURL(file);
  });
  event.target.value = '';
};

const removeImage = (index) => {
  imagePreviews.value.splice(index, 1);
  imageUrls.value.splice(index, 1);
};

const handleVideoChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    videoUrl.value = URL.createObjectURL(file); 
  }
};
const removeVideo = () => { videoUrl.value = null; };

const handleArModelChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    arModelUrl.value = "Local file selected: " + file.name;
  }
};
const removeArModel = () => { arModelUrl.value = null; };

onMounted(async () => {
  await fetchCategories();
  if (isEdit.value) await fetchProduct();
});

const fetchCategories = async () => {
  try {
    const res = await api.get('/categories'); 
    categories.value = res.data;
  } catch (e) {
    console.error("Could not load categories", e);
  }
};

const fetchProduct = async () => {
  try {
    const { data } = await api.get(`/artisan/products/${route.params.id}`);
    
    form.category_id = data.category_id;
    form.price = data.versions[0]?.price;
    form.stock = data.versions[0]?.stock;
    form.sku = data.versions[0]?.sku;

    const translations = data.versions[0]?.translations || [];
    const en = translations.find(t => t.language_id === 1); 
    if (en) { form.name_en = en.name; form.description_en = en.description; }
    const am = translations.find(t => t.language_id === 2); 
    if (am) { form.name_am = am.name; form.description_am = am.description; }
    const or = translations.find(t => t.language_id === 3); 
    if (or) { form.name_or = or.name; form.description_or = or.description; }

    if (data.images?.length) {
      const urls = data.images.map(img => img.image_path);
      imageUrls.value = urls;
      imagePreviews.value = urls;
    }

    if (data.versions[0]?.video_path) {
      videoUrl.value = data.versions[0].video_path;
    }

    if (data.versions[0]?.ar_model_path) {
      vrMode.value = 'upload';
      arModelUrl.value = data.versions[0].ar_model_path;
    }
  } catch (e) {
    console.error(e);
    alert('Failed to load product');
    router.push('/artisan/products');
  }
};

const submitForm = async () => {
  loading.value = true;
  errors.value = {};

  if (!form.name_en && !form.name_am && !form.name_or) {
    errors.value = { name: ['Please provide name in at least one language.'] };
    loading.value = false;
    return;
  }

  const formData = new FormData();
  formData.append('category_id', form.category_id);
  formData.append('price', form.price);
  formData.append('stock', form.stock);
  formData.append('sku', form.sku);
  formData.append('name_en', form.name_en);
  formData.append('description_en', form.description_en);
  formData.append('name_am', form.name_am);
  formData.append('description_am', form.description_am);
  formData.append('name_or', form.name_or);
  formData.append('description_or', form.description_or);
  formData.append('vr_request', vrMode.value === 'upload');

  if (isEdit.value) {
    formData.append('_method', 'PUT');
  }

  try {
    if (isEdit.value) {
      await api.post(`/artisan/products/${route.params.id}`, formData);
      alert('Product updated!');
    } else {
      await api.post('/artisan/products', formData);
      alert('Product created!');
    }
    router.push('/artisan/products');
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors;
      alert(Object.values(errors.value)[0][0]);
    } else {
      console.error(error);
      alert('An error occurred');
    }
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="min-h-screen bg-gray-50 py-10">
    <div class="max-w-3xl mx-auto px-4">
      <div class="bg-white p-8 rounded-lg shadow">
        <h2 class="text-2xl font-bold mb-6">
          {{ isEdit ? $t('product.edit_title') : $t('product.create_title') }}
        </h2>

        <form @submit.prevent="submitForm" class="space-y-6">
          
          <!-- LANGUAGE SECTIONS -->
          <div class="border p-4 rounded mb-4">
            <p class="text-center text-gray-400 text-sm">[ Put your English, Amharic, Oromo fields here ]</p>
          </div>

          <!-- COMMON FIELDS -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
              <select v-model="form.category_id" required class="mt-1 w-full border p-2 rounded bg-white">
                  <option value="" disabled>Select</option>
                  <template v-for="parent in categories" :key="parent.id">
                      <optgroup v-if="parent.children?.length" :label="parent.name">
                          <option v-for="child in parent.children" :key="child.id" :value="child.id">{{ child.name }}</option>
                      </optgroup>
                      <option v-else :value="parent.id">{{ parent.name }}</option>
                  </template>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Price (ETB) *</label>
              <input v-model="form.price" type="number" required class="w-full border p-2 rounded" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Stock *</label>
              <input v-model="form.stock" type="number" required class="w-full border p-2 rounded" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">SKU</label>
              <input v-model="form.sku" type="text" class="w-full border p-2 rounded" />
            </div>
          </div> 

          <!-- IMAGES -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Product Images</label>
            <div class="flex gap-4 flex-wrap">
              <div v-for="(img, i) in imagePreviews" :key="i" class="relative w-24 h-24 group">
                <img :src="img" class="w-full h-full object-cover rounded border" />
                <button type="button" @click="removeImage(i)" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 text-xs opacity-0 group-hover:opacity-100 transition">x</button>
              </div>
              
              <label v-if="imagePreviews.length < 5" class="w-24 h-24 border-dashed border-2 flex flex-col items-center justify-center text-gray-400 cursor-pointer hover:border-blue-500 hover:text-blue-500 transition">
                <span class="text-2xl">+</span>
                <span class="text-[10px]">Image</span>
                <input type="file" class="hidden" @change="handleImageChange" accept="image/*" multiple />
              </label>
            </div>
          </div>

          <!-- VIDEO -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Promo Video (Optional)</label>
            <div v-if="videoUrl" class="relative bg-gray-100 p-2 rounded border flex items-center gap-4">
               <video :src="videoUrl" class="h-20 rounded bg-black" controls></video>
               <button type="button" @click="removeVideo" class="text-red-500 hover:text-red-700 font-bold text-lg">Remove Video</button>
            </div>
            <div v-else>
              <label class="block w-full border-dashed border-2 p-6 text-center text-gray-400 cursor-pointer hover:border-blue-500 hover:text-blue-500 transition rounded">
                <span v-if="isUploadingVideo">Uploading...</span>
                <span v-else>+ Add Video (.mp4, .webm)</span>
                <input type="file" class="hidden" @change="handleVideoChange" accept="video/*" :disabled="isUploadingVideo" />
              </label>
            </div>
          </div>

          <!-- AR MODEL -->
          <div class="border-t border-gray-200 pt-6 mt-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">3D / Augmented Reality</h3>
              <div class="flex items-center gap-4 mb-4">
                  <label class="flex items-center cursor-pointer">
                      <input type="checkbox" v-model="vrMode" true-value="upload" false-value="none" class="w-4 h-4 text-purple-600 rounded">
                      <span class="ml-2 text-sm text-gray-700">Enable 3D/AR Model</span>
                  </label>
              </div>

              <div v-if="vrMode === 'upload'" class="space-y-3 bg-purple-50 p-4 rounded-lg border border-purple-200">
                  <label class="block text-sm font-medium text-purple-800">3D Model File</label>
                  <div v-if="arModelUrl" class="bg-green-50 border border-green-200 rounded p-3 text-sm text-green-700 flex justify-between items-center">
                      <span>✓ {{ arModelUrl }}</span>
                      <button type="button" @click="removeArModel" class="text-red-500 font-bold">Remove</button>
                  </div>
                  <div v-else>
                     <label class="block w-full border-2 border-dashed border-purple-300 p-6 text-center text-purple-500 cursor-pointer hover:bg-purple-100 transition rounded">
                        <span v-if="isUploadingModel">Uploading...</span>
                        <span v-else>+ Drop or select .glb / .gltf file</span>
                        <input type="file" class="hidden" @change="handleArModelChange" accept=".glb,.gltf" :disabled="isUploadingModel" />
                     </label>
                  </div>
              </div>
          </div>

          <!-- SUBMIT -->
          <div class="pt-4">
            <button 
              type="submit" 
              :disabled="loading"
              class="w-full bg-green-600 text-white py-3 rounded-md hover:bg-green-700 font-semibold shadow transition disabled:opacity-50"
            >
              {{ loading ? 'Saving...' : 'Save Product' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>