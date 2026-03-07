import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null,
    errors: {},
    message: null
  }),
  getters: {
    isAuthenticated: (state) => !!state.token,
    isAdmin: (state) => state.user?.role_id === 1,
    isSeller: (state) => state.user?.role_id === 3,
    isDelivery: (state) => state.user?.role_id === 4,
  },
  actions: {
    async fetchUser() {
      if (!this.token) return;
      try {
        const { data } = await axios.get('/api/user');
        this.user = data;
      } catch (error) {
        this.logout();
      }
    },
    
    async login(credentials) {
      try {
        await axios.get('/sanctum/csrf-cookie');
        const { data } = await axios.post('/api/login', credentials);
        this.token = data.access_token;
        localStorage.setItem('token', data.token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
        this.user = data.user;
        return true;
      } catch (error) {
        this.errors = error.response?.data?.errors || { email: [error.response.data.message] };
        return false;
      }
    },

    async register(route, form) {
      try {
        await axios.get('/sanctum/csrf-cookie');
        const { data } = await axios.post(`/api/register/${route}`, form);
        if (data.access_token) {
          this.token = data.access_token;
          localStorage.setItem('token', data.token);
          axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
          this.user = data.user;
        }
        this.message = data.message; // For pending accounts
        return true;
      } catch (error) {
        this.errors = error.response?.data?.errors || {};
        return false;
      }
    },

    logout() {
      axios.post('/api/logout');
      this.token = null;
      this.user = null;
      localStorage.removeItem('token');
      delete axios.defaults.headers.common['Authorization'];
    }
  }
});