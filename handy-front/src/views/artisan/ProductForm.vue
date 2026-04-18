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
const imagePreviews = ref([]);
const errors = ref({});

const form = reactive({
  category_id: '',
  price: '',
  stock: '',
  sku: '',
  
  // Languages
  name_en: '', description_en: '',
  name_am: '', description_am: '',
  name_or: '', description_or: '',
  
  images: [],
  ar_model: null
});

// --- VR State ---
const vrMode = ref('none'); 

onMounted(async () => {
  await fetchCategories();
  if (isEdit.value) {
    await fetchProduct();
  }
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
    // FIX: Added backticks
    const { data } = await api.get(`/artisan/products/${route.params.id}`);
    
    form.category_id = data.category_id;
    form.price = data.versions[0]?.price;
    form.stock = data.versions[0]?.stock;
    form.sku = data.versions[0]?.sku;

    // FIX: Added logical OR ()
    const translations = data.versions[0]?.translations || [];
    
    const en = translations.find(t => t.language_id === 1); 
    if (en) { form.name_en = en.name; form.description_en = en.description; }

    const am = translations.find(t => t.language_id === 2); 
    if (am) { form.name_am = am.name; form.description_am = am.description; }

    const or = translations.find(t => t.language_id === 3); 
    if (or) { form.name_or = or.name; form.description_or = or.description; }

    // UPDATE: Reels Pattern - Directly bind to what the backend returns (no hardcoded IP)
    if (data.images && Array.isArray(data.images)) {
        imagePreviews.value = data.images.map(img => img.image_path);
    } else {
        imagePreviews.value = [];
    }
    
    // Check if product already has a model
    if (data.versions[0]?.ar_model_path) {
        vrMode.value = 'upload';
    }

  } catch (e) {
    console.error(e);
    alert('Failed to load product data');
    router.push('/artisan/products');
  }
};

const handleFileChange = (event) => {
  const files = Array.from(event.target.files);
  files.forEach(file => {
    if (imagePreviews.value.length >= 5) return;
    form.images.push(file);
    const reader = new FileReader();
    reader.onload = (e) => imagePreviews.value.push(e.target.result);
    reader.readAsDataURL(file);
  });
};

const removeImage = (index) => {
  imagePreviews.value.splice(index, 1);
  form.images.splice(index, 1);
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
  formData.append('sku', form.sku || '');

  // Languages
  if (form.name_en) { formData.append('name_en', form.name_en); formData.append('description_en', form.description_en); }
  if (form.name_am) { formData.append('name_am', form.name_am); formData.append('description_am', form.description_am); }
  if (form.name_or) { formData.append('name_or', form.name_or); formData.append('description_or', form.description_or); }

  // Images - FIX: Added backticks
  for (let i = 0; i < form.images.length; i++) {
    formData.append(`images[${i}]`, form.images[i]);
  }
 
  // --- VR UPLOAD LOGIC ---
  if (vrMode.value === 'upload' && form.ar_model) {
      const fileName = form.ar_model.name.toLowerCase();
      const validExtensions = ['.glb', '.gltf'];
      const isValid = validExtensions.some(ext => fileName.endsWith(ext));
      if (!isValid) {
          errors.value.ar_model = ['Invalid file type. Please upload .glb or .gltf'];
          loading.value = false;
          return;
      }
      if (form.ar_model.size > 10240 * 1024) { 
          errors.value.ar_model = ['File size exceeds 10MB limit.'];
          loading.value = false;
          return;
      }
      
      formData.append('ar_model', form.ar_model);
  }
  // -------------------------------------------------

  try {
    let response; 

    if (isEdit.value) {
      formData.append('_method', 'PUT');
      // FIX: Added backticks
      response = await api.post(`/artisan/products/${route.params.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      alert('Product updated!');
    } else {
      response = await api.post('/artisan/products', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      alert('Product created!');
    }
    
    router.push('/artisan/products');

  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors;
      const firstError = Object.values(errors.value)[0];
      alert(firstError[0]);
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
          
          <!-- ENGLISH FIELDS -->
          <div class="border p-4 rounded">
            <h3 class="font-semibold text-gray-700 mb-3">{{ $t('common.english') }}</h3>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700">{{ $t('product.name') }}</label>
                <input v-model="form.name_en" type="text" class="mt-1 block w-full border rounded-md p-2" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">{{ $t('product.description') }}</label>
                <textarea v-model="form.description_en" rows="3" class="mt-1 block w-full border rounded-md p-2"></textarea>
              </div>
            </div>
          </div>

          <!-- AMHARIC FIELDS -->
          <div class="border p-4 rounded bg-gray-50">
            <h3 class="font-semibold text-gray-700 mb-3">{{ $t('common.amharic') }}</h3>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700">{{ $t('product.name') }}</label>
                <input v-model="form.name_am" type="text" class="mt-1 block w-full border rounded-md p-2" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">{{ $t('product.description') }}</label>
                <textarea v-model="form.description_am" rows="3" class="mt-1 block w-full border rounded-md p-2"></textarea>
              </div>
            </div>
          </div>

          <!-- OROMO FIELDS -->
          <div class="border p-4 rounded">
            <h3 class="font-semibold text-gray-700 mb-3">{{ $t('common.oromo') }}</h3>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700">{{ $t('product.name') }}</label>
                <input v-model="form.name_or" type="text" class="mt-1 block w-full border rounded-md p-2" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">{{ $t('product.description') }}</label>
                <textarea v-model="form.description_or" rows="3" class="mt-1 block w-full border rounded-md p-2"></textarea>
              </div>
            </div>
          </div>
          <!-- COMMON FIELDS -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t">
            <div>
              <label class="block text-sm font-medium text-gray-700">Category *</label>
              <select v-model="form.category_id" required class="mt-1 w-full border p-2 rounded bg-white focus:ring-2 focus:ring-emerald-500">
                  <option value="" disabled>Select a category</option>
                  <template v-for="parent in categories" :key="parent.id">
                      <optgroup v-if="parent.children && parent.children.length > 0" :label="parent.name">
                          <option v-for="child in parent.children" :key="child.id" :value="child.id">
                              {{ child.name }}
                          </option>
                      </optgroup>
                      <option v-else :value="parent.id">
                          {{ parent.name }}
                      </option>
                  </template>
              </select>
              <p v-if="errors.category_id" class="text-red-500 text-xs mt-1">{{ errors.category_id[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ $t('product.price') }} (ETB) *</label>
              <input v-model="form.price" type="number" required class="mt-1 block w-full border rounded-md p-2" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">{{ $t('product.stock') }} *</label>
              <input v-model="form.stock" type="number" required class="mt-1 block w-full border rounded-md p-2" />
            </div>
             <div>
              <label class="block text-sm font-medium text-gray-700">{{ $t('product.sku') }}</label>
              <input v-model="form.sku" type="text" class="mt-1 block w-full border rounded-md p-2" />
            </div>
          </div> 

          <!-- IMAGES -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('product.images') }}</label>
            <div class="flex gap-4 flex-wrap">
              <div v-for="(img, i) in imagePreviews" :key="i" class="relative w-24 h-24">
                <img :src="img" class="w-full h-full object-cover rounded" />
                <button type="button" @click="removeImage(i)" class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center hover:bg-red-600">x</button>
              </div>
              
              <label class="w-24 h-24 border-dashed border-2 flex items-center justify-center text-gray-400 cursor-pointer hover:border-green-500 transition">
                <span class="text-2xl">+</span>
                <input type="file" class="hidden" @change="handleFileChange" accept="image/*" multiple />
              </label>
            </div>
          </div>

          <!-- VR SECTION -->
          <div class="border-t border-gray-200 pt-6 mt-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">3D / Augmented Reality</h3>
              
              <!-- Toggle Switch -->
              <div class="flex items-center gap-4 mb-4">
                  <label class="flex items-center cursor-pointer">
                      <input 
                          type="checkbox" 
                          v-model="vrMode" 
                          true-value="upload" 
                          false-value="none" 
                          class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500"
                      >
                      <span class="ml-2 text-sm text-gray-700">I have a 3D model to upload</span>
                  </label>
              </div>
              <!-- Upload Input -->
              <div v-if="vrMode === 'upload'" class="space-y-3 bg-purple-50 p-4 rounded-lg border border-purple-200">
                  <label class="block text-sm font-medium text-purple-800">Upload 3D Model File</label>
                  <p class="text-xs text-purple-600">
                      Format: <span class="font-bold">.glb</span> or <span class="font-bold">.gltf</span> (Max 10MB).
                  </p>
                  <input 
                      type="file" 
                      accept=".glb,.gltf" 
                      @change="e => form.ar_model = e.target.files[0]" 
                      class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-100 file:text-purple-700 hover:file:bg-purple-200 cursor-pointer"
                  >
                  <p v-if="form.ar_model" class="text-green-600 text-xs mt-1 font-medium">
                      Selected: {{ form.ar_model.name }}
                  </p>
                  <p v-if="errors.ar_model" class="text-red-500 text-xs mt-1">{{ errors.ar_model[0] }}</p>
              </div>
          </div>

          <div class="pt-4">
            <button 
              type="submit" 
              :disabled="loading"
              class="w-full bg-green-600 text-white py-3 rounded-md hover:bg-green-700 font-semibold shadow transition"
            >
              {{ loading ? $t('common.saving') : $t('common.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>