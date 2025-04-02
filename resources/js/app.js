import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import { createPinia } from 'pinia';
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate';
import axios from 'axios';

import SqlValidator from './components/SqlValidator.vue';

// Configure Axios
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const token = document.querySelector('meta[name="csrf-token"]');
if (token) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
}

// Create Pinia store
const pinia = createPinia();
pinia.use(piniaPluginPersistedstate);

// Set up router
const routes = [
  { path: '/', component: SqlValidator, name: 'home' },
  // Add more routes as needed in the future
];

const router = createRouter({
  history: createWebHistory(),
  routes
});

// Create and mount the Vue app
const app = createApp(SqlValidator);
app.use(pinia);
app.use(router);
app.mount('#app');
