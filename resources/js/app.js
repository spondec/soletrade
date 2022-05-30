/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */


require("./bootstrap");

import {createApp} from "vue";
import * as VueRouter from "vue-router";

const app = createApp({});

import '../css/app.css'
import PriceChart from "./pages/PriceChart";
import Dashboard from "./pages/Dashboard";
import ApiService from "./services/ApiService";
import ErrorPage from "./pages/Error";

/**
 * The following block of code may be used to automatically register your
 * Vue components. It will recursively scan this directory for the Vue
 * components and automatically register them with their "basename".
 *
 * Eg. ./components/ExampleComponent.vue -> <example-component></example-component>
 */

// const files = require.context('./', true, /\.vue$/i)
// files.keys().map(key => Vue.component(key.split('/').pop().split('.')[0], files(key).default))

app.component('dashboard-page', require('./pages/Dashboard.vue').default);
app.component('main-layout', require('./layouts/Main.vue').default);
app.component('card-table', require('./components/CardTable.vue').default);

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const routes = [
    {path: '/', component: Dashboard},
    {path: '/chart', component: PriceChart},
    {path: '/error', component: ErrorPage},
];

const router = VueRouter.createRouter({
    // 4. Provide the history implementation to use. We are using the hash history for simplicity here.
    history: VueRouter.createWebHashHistory(),
    routes, // short for `routes: routes`
})

ApiService.setErrorHandler((error) =>
{
    console.log(error);
    router.push('/error');
});

app.use(router);
app.mount('#app')