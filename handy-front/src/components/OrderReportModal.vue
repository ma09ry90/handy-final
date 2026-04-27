<template>
  <!-- Modal Overlay -->
  <transition 
    enter-active-class="transition ease-out duration-200"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition ease-in duration-150"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div 
      v-if="isVisible" 
      class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm"
      @click="handleClose"
    ></div>
  </transition>

  <!-- Modal Content -->
  <transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="opacity-0 translate-y-4 sm:scale-95"
    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
    leave-active-class="transition ease-in duration-150"
    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
    leave-to-class="opacity-0 translate-y-4 sm:scale-95"
  >
    <div 
      v-if="isVisible" 
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
      <div 
        class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 relative"
        @click.stop
      >
        <!-- Close Button -->
        <button 
          @click="handleClose" 
          class="absolute top-4 rtl:top-4 rtl:left-4 ltr:right-4 text-gray-400 hover:text-gray-600 transition-colors"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>

        <!-- Header -->
        <div class="mb-6">
          <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
          </div>
          <h3 class="text-xl font-bold text-gray-800">{{ t('report.title') }}</h3>
          <p v-if="productName" class="text-sm text-gray-500 mt-1">
            {{ t('report.context_product') }}: <span class="font-semibold text-gray-700">{{ productName }}</span>
          </p>
        </div>

        <!-- Success State -->
        <div v-if="isSuccess" class="text-center py-6">
          <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <p class="text-green-700 font-semibold text-lg">{{ t('report.success') }}</p>
        </div>

        <!-- Form State -->
        <form v-else @submit.prevent="submitReport">
          
          <!-- Validation Errors -->
          <div v-if="validationErrors.length > 0" class="mb-4 bg-red-50 border border-red-200 text-red-600 text-sm p-3 rounded-xl">
            <ul class="list-disc list-inside space-y-1">
              <li v-for="(err, index) in validationErrors" :key="index">{{ err }}</li>
            </ul>
          </div>

          <!-- General API Error -->
          <div v-if="generalError" class="mb-4 bg-red-50 border border-red-200 text-red-600 text-sm p-3 rounded-xl">
            {{ generalError }}
          </div>

          <!-- Reason Select -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ t('report.reason') }} <span class="text-red-500">*</span>
            </label>
            <select 
              v-model="form.reason" 
              class="w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
              :class="{ 'border-red-400 ring-1 ring-red-400': validationErrors.includes(t('report.reason_required')) }"
            >
              <option value="" disabled selected>{{ t('report.select_reason') }}</option>
              <option value="damaged">{{ t('report.reasons.damaged') }}</option>
              <option value="wrong_item">{{ t('report.reasons.wrong_item') }}</option>
              <option value="not_as_described">{{ t('report.reasons.not_as_described') }}</option>
              <option value="scam_fraud">{{ t('report.reasons.scam_fraud') }}</option>
              <option value="other">{{ t('report.reasons.other') }}</option>
            </select>
          </div>

          <!-- Description Textarea -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              {{ t('report.description') }}
            </label>
            <textarea 
              v-model="form.description" 
              rows="4" 
              :placeholder="t('report.placeholder')"
              class="w-full border border-gray-300 rounded-xl px-4 py-3 text-gray-800 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"
            ></textarea>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 rtl:flex-row-reverse">
            <button 
              type="button" 
              @click="handleClose"
              class="flex-1 px-4 py-3 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors"
            >
              {{ t('report.cancel') }}
            </button>
            <button 
              type="submit" 
              :disabled="isSubmitting || !form.reason"
              class="flex-1 px-4 py-3 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              <svg v-if="isSubmitting" class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ t('report.submit') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../plugins/axios'

const { t } = useI18n()

const props = defineProps({
  isVisible: {
    type: Boolean,
    default: false
  },
  productId: {
    type: Number,
    default: null
  },
  userId: {
    type: Number,
    default: null
  },
  productName: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['close', 'submitted'])

// Form State
const form = ref({
  reason: '',
  description: ''
})

const isSubmitting = ref(false)
const isSuccess = ref(false)
const validationErrors = ref([])
const generalError = ref('')

// Reset form when modal opens/closes
watch(() => props.isVisible, (val) => {
  if (val) {
    form.value = { reason: '', description: '' }
    isSuccess.value = false
    validationErrors.value = []
    generalError.value = ''
  }
})

const handleClose = () => {
  if (!isSubmitting.value) {
    emit('close')
  }
}

const submitReport = async () => {
  validationErrors.value = []
  generalError.value = ''
  
  if (!form.value.reason) {
    validationErrors.value.push(t('report.reason_required'))
    return
  }

  try {
    isSubmitting.value = true
    
    const payload = {
      reason: form.value.reason,
      description: form.value.description || null,
      reported_product_id: props.productId || null,
      reported_user_id: props.userId || null,
    }

    await api.post('/reports', payload)
    
    isSuccess.value = true
    
    // Close modal and notify parent after a short delay
    setTimeout(() => {
      emit('submitted')
      handleClose()
    }, 1500)

  } catch (error) {
    if (error.response?.status === 422 && error.response?.data?.errors) {
      // Handle Laravel validation errors
      const laravelErrors = error.response.data.errors
      validationErrors.value = Object.values(laravelErrors).flat()
    } else {
      generalError.value = error.response?.data?.message || t('report.error_generic')
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>