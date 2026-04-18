import { defineStore } from 'pinia'
import api from '@/plugins/axios'
import { useAuthStore } from './auth'
import router from '@/router' // Import router for redirection

export const useWishlistStore = defineStore('wishlist', {
  state: () => ({
    items: [],
    likedIds: [] 
  }),
  actions: {
    async fetchWishlist() {
      const authStore = useAuthStore()
      if (!authStore.isAuthenticated) return // Don't fetch if guest

      const res = await api.get('/wishlist')
      this.items = res.data
      this.likedIds = this.items.map(item => item.id)
    },

    async toggleWishlist(productId) {
      const authStore = useAuthStore()

      // --- REDIRECT GUESTS ---
      if (!authStore.isAuthenticated) {
        alert('Please log in to save items to your wishlist.')
        router.push({ name: 'Login' }) // Make sure your login route has name: 'Login'
        return
      }

      // --- LOGGED IN: Toggle via API ---
      const res = await api.post('/wishlist/toggle', { product_id: productId })
      if (res.data.status === 'added') {
        this.likedIds.push(productId)
      } else {
        this.likedIds = this.likedIds.filter(id => id !== productId)
      }
      await this.fetchWishlist()
    }
  }
})