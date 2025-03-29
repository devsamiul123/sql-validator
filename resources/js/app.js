// resources/js/app.js

import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import { createPinia } from 'pinia';
import App from './components/App.vue';
import Home from './components/Home.vue';
import SQLValidator from './components/SQLValidator.vue';

import 'bootstrap/dist/css/bootstrap.css';
import 'bootstrap/dist/js/bootstrap.js';

// Define routes
const routes = [
    {
        path: '/',
        name: 'home',
        component: Home
    },
    {
        path: '/validator',
        name: 'validator',
        component: SQLValidator
    }
];

// Configure router
const router = createRouter({
    history: createWebHistory(),
    routes
});

// Create pinia store (Vue 3's recommended state management)
const pinia = createPinia();

// Create and mount the app
const app = createApp(App);
app.use(router);
app.use(pinia);
app.mount('#app');