<template>
  <div class="max-w-4xl mx-auto space-y-6">
    
    <!-- Approval Status Banner -->
    <div v-if="profile.approval_status" 
         class="p-4 rounded-xl border flex items-center gap-3"
         :class="statusClass">
      <span class="text-2xl">{{ statusIcon }}</span>
      <div>
        <h3 class="font-bold" :class="statusTextClass">{{ $t(`profile.status_${profile.approval_status}`) }}</h3>
        <p v-if="profile.approval_status === 'rejected'" class="text-sm opacity-80">
          {{ $t('profile.rejected_message') }}
        </p>
      </div>
    </div>

    <form @submit.prevent="submitForm" enctype="multipart/form-data">
      
      <!-- Shop Information -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
          <span class="w-8 h-8 bg-green-100 text-green-600 rounded-lg flex items-center justify-center text-sm">🏪</span>
          {{ $t('profile.shop_info') }}
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Shop Name -->
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profile.shop_name') }} <span class="text-red-500">*</span></label>
            <input v-model="form.shop_name" type="text" required
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                   :class="{ 'border-red-400': errors.shop_name }">
            <p v-if="errors.shop_name" class="text-red-500 text-xs mt-1">{{ errors.shop_name[0] }}</p>
          </div>

          <!-- Shop Description -->
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profile.shop_description') }}</label>
            <textarea v-model="form.shop_description" rows="3"
                      class="w-full border border-gray-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors resize-none"></textarea>
          </div>

          <!-- Slang / Tagline -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profile.slang') }}</label>
            <input v-model="form.slang" type="text"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
          </div>

          <!-- Shop Logo -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profile.shop_logo') }}</label>
            <div class="flex items-center gap-4">
              <div class="w-16 h-16 rounded-xl bg-gray-100 border-2 border-dashed border-gray-300 overflow-hidden flex items-center justify-center">
                <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-cover">
                <span v-else class="text-gray-400 text-2xl">📷</span>
              </div>
              <div>
                <input type="file" @change="handleLogoUpload" accept="image/*" class="hidden" ref="logoInput">
                <button type="button" @click="$refs.logoInput.click()" 
                        class="text-sm text-green-600 font-medium hover:text-green-800">
                  {{ profile.shop_logo_path ? $t('profile.change_logo') : $t('profile.upload_logo') }}
                </button>
                <p v-if="profile.shop_logo_path && !logoPreview" class="text-xs text-gray-500">{{ $t('profile.current_file') }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Business & Legal Information -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
          <span class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center text-sm">📋</span>
          {{ $t('profile.business_info') }}
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profile.shop_address_id') }}</label>
            <input v-model="form.shop_address_id" type="number"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profile.business_license_number') }}</label>
            <input v-model="form.business_license_number" type="text"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profile.tax_id') }}</label>
            <input v-model="form.tax_id" type="text"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
          </div>
        </div>
      </div>

      <!-- Bank Information -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
          <span class="w-8 h-8 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center text-sm">🏦</span>
          {{ $t('profile.bank_details') }}
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profile.bank_name') }}</label>
            <input v-model="form.bank_name" type="text"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profile.bank_account_name') }}</label>
            <input v-model="form.bank_account_name" type="text"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profile.bank_account_number') }}</label>
            <input v-model="form.bank_account_number" type="text"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors">
          </div>
        </div>
      </div>

      <!-- Documents Upload -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
          <span class="w-8 h-8 bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center text-sm">📁</span>
          {{ $t('profile.documents') }}
        </h2>

        <div class="space-y-4">
          <!-- Identity Document -->
          <div class="flex flex-col md:flex-row md:items-center justify-between p-4 border border-gray-200 rounded-xl">
            <div>
              <p class="font-medium text-gray-800">{{ $t('profile.identity_document') }}</p>
              <p v-if="profile.identity_document_id" class="text-xs text-green-600 mt-1">✅ {{ $t('profile.submitted') }}</p>
              <p v-else class="text-xs text-gray-500 mt-1">{{ $t('profile.not_submitted') }}</p>
            </div>
            <input type="file" @change="addFile('identity_document', $event)" class="hidden" :ref="el => fileInputs.identity_document = el">
            <button type="button" @click="fileInputs.identity_document.click()" class="mt-2 md:mt-0 text-sm bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 font-medium">
              {{ profile.identity_document_id ? $t('profile.replace') : $t('profile.upload') }}
            </button>
          </div>

          <!-- Business License Document -->
          <div class="flex flex-col md:flex-row md:items-center justify-between p-4 border border-gray-200 rounded-xl">
            <div>
              <p class="font-medium text-gray-800">{{ $t('profile.business_license_document') }}</p>
              <p v-if="profile.business_license_document_id" class="text-xs text-green-600 mt-1">✅ {{ $t('profile.submitted') }}</p>
              <p v-else class="text-xs text-gray-500 mt-1">{{ $t('profile.not_submitted') }}</p>
            </div>
            <input type="file" @change="addFile('business_license_document', $event)" class="hidden" :ref="el => fileInputs.business_license_document = el">
            <button type="button" @click="fileInputs.business_license_document.click()" class="mt-2 md:mt-0 text-sm bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 font-medium">
              {{ profile.business_license_document_id ? $t('profile.replace') : $t('profile.upload') }}
            </button>
          </div>

          <!-- Tax Document -->
          <div class="flex flex-col md:flex-row md:items-center justify-between p-4 border border-gray-200 rounded-xl">
            <div>
              <p class="font-medium text-gray-800">{{ $t('profile.tax_registration_document') }}</p>
              <p v-if="profile.tax_registration_document_id" class="text-xs text-green-600 mt-1">✅ {{ $t('profile.submitted') }}</p>
              <p v-else class="text-xs text-gray-500 mt-1">{{ $t('profile.not_submitted') }}</p>
            </div>
            <input type="file" @change="addFile('tax_registration_document', $event)" class="hidden" :ref="el => fileInputs.tax_registration_document = el">
            <button type="button" @click="fileInputs.tax_registration_document.click()" class="mt-2 md:mt-0 text-sm bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 font-medium">
              {{ profile.tax_registration_document_id ? $t('profile.replace') : $t('profile.upload') }}
            </button>
          </div>

          <!-- Additional Documents -->
          <div class="p-4 border border-dashed border-gray-300 rounded-xl">
            <p class="font-medium text-gray-800 mb-2">{{ $t('profile.additional_documents') }}</p>
            <p class="text-xs text-gray-500 mb-3">{{ $t('profile.additional_documents_hint') }}</p>
            <input type="file" multiple @change="addFile('additional_documents', $event)" class="hidden" ref="additionalDocsInput">
            <button type="button" @click="$refs.additionalDocsInput.click()" class="text-sm bg-green-50 text-green-700 px-4 py-2 rounded-lg hover:bg-green-100 font-medium">
              + {{ $t('profile.add_files') }}
            </button>
            <div v-if="form.additional_documents.length" class="mt-3 space-y-1">
              <p v-for="(file, index) in form.additional_documents" :key="index" class="text-xs text-gray-600 bg-gray-50 p-2 rounded flex justify-between">
                <span class="truncate">{{ file.name }}</span>
                <button type="button" @click="removeAdditionalFile(index)" class="text-red-500 hover:text-red-700 font-bold">X</button>
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Submit Button -->
      <div class="flex justify-end">
        <button type="submit" :disabled="isSubmitting"
                class="px-8 py-3 bg-green-600 text-white font-semibold rounded-xl shadow-md hover:bg-green-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
          <svg v-if="isSubmitting" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ isSubmitting ? $t('profile.saving') : $t('profile.save') }}
        </button>
      </div>
      
      <!-- Success Message -->
      <transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0">
        <div v-if="showSuccess" class="fixed bottom-6 right-6 bg-green-600 text-white px-6 py-3 rounded-xl shadow-lg flex items-center gap-2 font-medium z-50">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          {{ $t('profile.success') }}
        </div>
      </transition>

    </form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/plugins/axios'

const { t } = useI18n()

const profile = ref({
  approval_status: null,
  shop_logo_path: null,
  identity_document_id: null,
  business_license_document_id: null,
  tax_registration_document_id: null
})

// ✅ FIX: Only ONE form declaration containing all fields
const form = reactive({
  shop_name: '',
  shop_description: '',
  slang: '',
  shop_address_id: '',
  business_license_number: '',
  tax_id: '',
  bank_name: '',
  bank_account_name: '',
  bank_account_number: '',
  additional_documents: [] 
})

const filesToUpload = reactive({
  shop_logo: null,
  identity_document: null,
  business_license_document: null,
  tax_registration_document: null,
})

const fileInputs = reactive({})
const logoPreview = ref(null)
const isSubmitting = ref(false)
const showSuccess = ref(false)
const errors = ref({})

// Status Styling
const statusClass = computed(() => {
  const status = profile.value.approval_status;
  if (status === 'approved') return 'bg-green-50 border-green-200 text-green-800';
  if (status === 'rejected') return 'bg-red-50 border-red-200 text-red-800';
  return 'bg-amber-50 border-amber-200 text-amber-800';
})

const statusIcon = computed(() => {
  const status = profile.value.approval_status;
  if (status === 'approved') return '✅';
  if (status === 'rejected') return '❌';
  return '⏳';
})

const statusTextClass = computed(() => {
  const status = profile.value.approval_status;
  if (status === 'approved') return 'text-green-800';
  if (status === 'rejected') return 'text-red-800';
  return 'text-amber-800';
})

// File Handlers
const handleLogoUpload = (event) => {
  const file = event.target.files[0];
  if (file) {
    filesToUpload.shop_logo = file;
    logoPreview.value = URL.createObjectURL(file);
  }
}

const addFile = (key, event) => {
  const file = event.target.files[0];
  if (file) {
    filesToUpload[key] = file;
  }
}

const removeAdditionalFile = (index) => {
  form.additional_documents.splice(index, 1);
}

// Fetch Profile
const fetchProfile = async () => {
  try {
    const response = await api.get('/artisan/profile')
    const data = response.data.profile
    
    profile.value = data
    
    // Populate form
    Object.keys(form).forEach(key => {
      if (data[key] !== null && data[key] !== undefined) {
        form[key] = data[key]
      }
    })

    if (data.shop_logo_path) {
      logoPreview.value = `/storage/${data.shop_logo_path}`
    }
  } catch (error) {
    console.error("Error fetching profile:", error)
  }
}

// Submit Form
const submitForm = async () => {
  isSubmitting.value = true
  errors.value = {}
  
  try {
    const formData = new FormData();
    
    // Append text fields
    Object.keys(form).forEach(key => {
      if (key === 'additional_documents') return; // handle separately
      formData.append(key, form[key] || '');
    });

    // Append single files
    Object.keys(filesToUpload).forEach(key => {
      if (filesToUpload[key] instanceof File) {
        formData.append(key, filesToUpload[key]);
      }
    });

    // Append multiple files
    if (form.additional_documents.length > 0) {
      form.additional_documents.forEach(file => {
        formData.append('additional_documents[]', file);
      });
    }

    // Use PUT or POST. Laravel usually handles PUT via _method=PUT in FormData
    formData.append('_method', 'PUT');

    await api.post('/artisan/profile', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    showSuccess.value = true
    setTimeout(() => showSuccess.value = false, 3000)
    
    // Refresh data to get new IDs/Status
    await fetchProfile()

  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors
    } else {
      console.error("Update error:", error)
    }
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  fetchProfile()
})
</script>