import axios from 'axios';

// 1. Check if the Environment Variable exists
// import.meta.env.VITE_API_BASE_URL will be EMPTY on local (so we use '/api')
// On Vercel, it will be 'https://....ngrok...'
const baseURL = import.meta.env.VITE_API_BASE_URL || '/api';

const api = axios.create({
    baseURL: baseURL, 
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'ngrok-skip-browser-warning': 'true'//this heder thells the bgrok to skip the warning 
    },
    // withCredentials: true // Keep this commented if you are using Token Auth (Bearer Token)
});

// DEBUG LINE: Check console to see which URL it is using
console.log('✅ AXIOS LOADED. Base URL is:', api.defaults.baseURL);

// Interceptor: Add Token AND Language Header
api.interceptors.request.use(config => {
    const token = localStorage.getItem('token');
    const locale = localStorage.getItem('locale') || 'en';

    if (token) {
        config.headers.Authorization = 'Bearer ' + token;
    }
    
    config.headers['Accept-Language'] = locale; 

    return config;
}, error => {
    return Promise.reject(error);
});

// Response Interceptor
api.interceptors.response.use(
    response => response,
    error => {
        if (error.response && error.response.status === 401) {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            // Avoid infinite loops
            if (window.location.pathname !== '/login') {
                // window.location.href = '/login'; 
            }
        }
        return Promise.reject(error);
    }
);

export default api;