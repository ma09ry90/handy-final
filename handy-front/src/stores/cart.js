import { defineStore } from 'pinia'
import api from '@/plugins/axios'
import { useAuthStore } from './auth'

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [],
    count: 0,
    error: null,       // <-- ADDED: Tracks stock errors
    isLoading: false    // <-- ADDED: Tracks API loading state
  }),
  
  actions: {
    saveLocal() {
      localStorage.setItem('guest_cart', JSON.stringify(this.items))
      this.count = this.items.length
    },

    async addToCart(productId, versionId, qty = 1) {
      this.error = null // Clear errors when adding new items
      const authStore = useAuthStore()
      
      if (authStore.isAuthenticated) {
        try {
          await api.post('/cart', { product_id: productId, product_version_id: versionId, quantity: qty })
          await this.fetchCart()
        } catch (error) {
          this.error = error.response?.data?.message || 'Failed to add to cart.'
          alert(this.error)
        }
      } else {
        const existingIndex = this.items.findIndex(i => i.version_id === versionId)
        if (existingIndex !== -1) { this.items[existingIndex].quantity += qty }
        else { this.items.push({ product_id: productId, version_id: versionId, quantity: qty }) }
        this.saveLocal()
      }
    },

    async fetchCart() {
      try {
        const res = await api.get('/cart')
        this.items = res.data
        this.count = this.items.length
        this.error = null // Clear error if cart fetches successfully
      } catch (e) { 
        console.error("Failed to fetch DB cart", e) 
      }
    },

    async loginMerge() {
      const guestItems = JSON.parse(localStorage.getItem('guest_cart') || '[]')
      if (guestItems.length > 0) {
        try {
          await api.post('/cart/merge', { items: guestItems })
          localStorage.removeItem('guest_cart')
        } catch (e) { console.error("Cart merge failed", e) }
      }
      await this.fetchCart()
    },

    loadLocal() {
      this.items = JSON.parse(localStorage.getItem('guest_cart') || '[]')
      this.count = this.items.length
    },

    async updateQuantity(versionId, newQty) {
      const authStore = useAuthStore()
      
      // FIX: Immediately clear the error when user tries to change quantity
      this.error = null 
      
      // FIX: Treat 0 or negative as a removal
      if (newQty < 1) {
        return this.removeItem(versionId)
      }
      
      if (authStore.isAuthenticated) {
        this.isLoading = true // Start loading
        const dbItem = this.items.find(i => i.version_id === versionId)
        
        if (dbItem?.id) {
          try {
            await api.put(`/cart/${dbItem.id}`, { quantity: newQty })
            await this.fetchCart()
          } catch (error) {
            // Catch the 422 max stock error
            const errorMsg = error.response?.data?.message || 'Cannot exceed available stock.'
            
            // Set the error state so the UI can disable checkout
            this.error = errorMsg
            alert(errorMsg) 
            
            // Re-fetch to ensure UI shows the actual quantity saved in DB
            try {
              await this.fetchCart()
            } catch (fetchError) {
              console.error("Cart refresh failed", fetchError)
            }
          } finally {
            // FIX: ALWAYS stop loading, whether it succeeded or failed
            this.isLoading = false
          }
        } else {
          this.isLoading = false
        }
      } else {
        // Guest cart local state update
        const item = this.items.find(i => i.version_id === versionId)
        if (item) { 
          item.quantity = newQty; 
          this.saveLocal() 
        }
      }
    },

    async removeItem(itemId) {
      this.error = null // Clear errors on removal
      const authStore = useAuthStore()
      
      if (authStore.isAuthenticated) {
        await api.delete(`/cart/${itemId}`)
        await this.fetchCart()
      } else {
        this.items = this.items.filter(i => i.version_id !== itemId)
        this.saveLocal()
      }
    },

    // Clear cart after successful order
    clearCart() {
      this.items = []
      this.count = 0
      this.error = null // Clear errors
      localStorage.removeItem('guest_cart')
    }
  }
})