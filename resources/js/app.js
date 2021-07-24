/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require("./bootstrap");

import {createApp, h} from "vue";

const app = createApp({});

import '../css/app.css'
import routes from './routes'


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
app.component('v-link', require('./components/VLink.vue').default);

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const SimpleRouter = {
    data: () => ({
        currentRoute: window.location.pathname
    }),

    computed: {
        ViewComponent()
        {
            const matchingPage = routes[this.currentRoute] || '404'
            const component = require(`./pages/${matchingPage}.vue`).default;
            if (component.title) document.title = component.title;
            return component
        }
    },

    render()
    {
        return h(this.ViewComponent)
    },

    created()
    {
        window.addEventListener('popstate', () =>
        {
            this.currentRoute = window.location.pathname
        })
    }
}

createApp(SimpleRouter).mount('#app')






