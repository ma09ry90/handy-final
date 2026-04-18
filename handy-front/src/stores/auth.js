import { defineStore } from 'pinia';
import api from '@/plugins/axios';
import router from '@/router';
import i18n from '@/plugins/i18n';
import { useCartStore } from '@/stores/cart';

export const useAuthStore = defineStore('auth', {
  state: function () {
    return {
      user: null,
      token: null,
      errors: null,
      isLoading: false,
      permissions: [] 
    };
  },

  getters: {
    isAuthenticated: function (state) {
      return !!state.token;
    },
    
    // ✅ ADDED: This getter is required for the Router Navigation Guard
    userRole: function (state) {
      return state.user?.role_id || null;
    },

    hasPermission: (state) => (permissionName) => {
      // Fixed syntax: Added missing logical OR ()
      return state.permissions.includes(permissionName) || state.permissions.includes('manage_all');
    }
  },

  actions: {
    initialize: function () {
      const storedToken = localStorage.getItem('token');
      const storedUser = localStorage.getItem('user');
      
      if (storedToken) {
        this.token = storedToken;
      }
      if (storedUser) {
        try {
          this.user = JSON.parse(storedUser);

          // Fixed syntax: Added missing logical OR ()
          this.permissions = this.user?.permissions || [];

          if (this.user && this.user.locale) {
            i18n.global.locale.value = this.user.locale;
            localStorage.setItem('locale', this.user.locale);
          }
        } catch (e) {
          console.error('Error parsing user data');
        }
      }
    },

    login: async function (credentials) {
      this.errors = null;
      this.isLoading = true;

      try {
        const response = await api.post('/login', credentials);

        this.token = response.data.access_token;
        this.user = response.data.user;

        // Fixed syntax: Added missing logical OR ()
        this.permissions = response.data.user.permissions || [];

        localStorage.setItem('token', this.token);
        localStorage.setItem('user', JSON.stringify(this.user));

        if (this.user && this.user.locale) {
          i18n.global.locale.value = this.user.locale;
          localStorage.setItem('locale', this.user.locale);
        }

        try {
          const cartStore = useCartStore();
          await cartStore.loginMerge();
        } catch (cartError) {
          console.warn('Cart merge failed (non-critical):', cartError);
        }

        // Redirect Logic
        const isAdmin = this.permissions.some(p => 
          ['manage_all', 'assign_driver', 'approve_withdrawal', 'manage_products'].includes(p)
        );

        if (isAdmin) {
          router.push('/admin/dashboard');
        } else if (this.user.role_id === 2) {
          router.push('/artisan/dashboard');
        } else if (this.user.role_id === 3) {
          router.push('/delivery/dashboard');
        } else {
          router.push('/');
        }

      } catch (error) {
        console.error('Login error:', error);
        
        const errorResponse = error.response;

        if (errorResponse) {
          const status = errorResponse.status;
          const data = errorResponse.data;

          // Fixed syntax: Added missing logical OR ()
          if (status === 422 || status === 401) {
            if (data && data.errors) {
              this.errors = data.errors;
            } else if (data && data.message) {
              this.errors = { email: [data.message] };
            }
          } else if (status === 403) {
            if (data && data.message) {
              this.errors = { email: [data.message] };
            } else {
              this.errors = { email: ['Account access issue.'] };
            }
          }
        } else {
          this.errors = { email: ['Network error. Please try again.'] };
        }
      } finally {
        this.isLoading = false;
      }
    },
    logout: async function () {
      try {
        await api.post('/logout');
      } catch (e) {
        console.log('Logout API error', e);
      } 
      
      this.token = null;
      this.user = null;
      this.permissions = []; 
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      
      router.push('/login');
    }
  }
});