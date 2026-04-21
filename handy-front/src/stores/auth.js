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
    
    userRole: function (state) {
      // FIX: Normalize to number — API can return string "4" or number 4
      // Without this, router guard's "userRole === 4" fails on type mismatch
      const roleId = state.user?.role_id;
      return roleId != null ? Number(roleId) : null;
    },

    hasPermission: (state) => (permissionName) => {
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

        // FIX: Use normalized number for role matching
        const roleId = Number(this.user.role_id);

        // FIX: All seeder permission names included — no mismatches
        const isAdmin = this.permissions.some(p => [
          'manage_all',
          'approve_delivery_person',
          'assign_driver',
          'approve_withdrawal',
          'manage_products',
          'view_orders',
          'manage_delivery_status',
          'view_transactions',
          'manage_escrow',
          'hide_product',
          'block_artisan',
          'handle_reports',
          'view_reports',
          'manage_users',
          'manage_settings',
        ].includes(p));

        // FIX: role_id 4 = admin (was checking 1 = buyer)
        if (roleId === 4 && isAdmin) {
          router.push('/admin/dashboard');
        } else if (roleId === 2) {
          router.push('/artisan/dashboard');
        } else if (roleId === 3) {
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