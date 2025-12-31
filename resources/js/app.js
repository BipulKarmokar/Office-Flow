import { createApp } from 'vue';
import App from './App.vue';
import '../scss/app.scss';

// Mount Vue App
const appElement = document.getElementById('office-utilities-app');
if (appElement) {
    createApp(App).mount('#office-utilities-app');
}
