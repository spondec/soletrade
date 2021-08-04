<template>
  <nav class="navbar navbar-expand-md navbar-dark shadow-sm">
    <div class="container">
      <v-link class="navbar-brand" href="/">{{ appName }}</v-link>

      <div class="navbar-collapse">
        <!-- Left Side Of Navbar -->
        <ul class="navbar-nav mr-auto">
          <li class="nav-item">
            <v-link class="nav-link" href="/chart">Chart</v-link>
          </li>
        </ul>

        <!-- Right Side Of Navbar -->
        <ul class="navbar-nav ml-auto">
          <!-- Authentication Links -->
          <!--          @guest-->
          <!--          @if (Route::has('login'))-->
          <!--          <li class="nav-item">-->
          <!--            <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>-->
          <!--          </li>-->
          <!--          @endif-->

          <!--          @if (Route::has('register'))-->
          <!--          <li class="nav-item">-->
          <!--            <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>-->
          <!--          </li>-->
          <!--          @endif-->
          <!--          @else-->
          <!--          <li class="nav-item dropdown">-->
          <!--            <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"-->
          <!--               data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>-->
          <!--              {{ Auth::user()->name }}-->
          <!--            </a>-->

          <!--            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">-->
          <!--              <a class="dropdown-item" href="{{ route('logout') }}"-->
          <!--                 onclick="event.preventDefault();-->
          <!--                                                     document.getElementById('logout-form').submit();">-->
          <!--                {{ __('Logout') }}-->
          <!--              </a>-->

          <!--              <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">-->
          <!--                @csrf-->
          <!--              </form>-->
          <!--            </div>-->
          <!--          </li>-->
          <!--          @endguest-->
        </ul>
      </div>
    </div>
  </nav>
  <div class="container text-white">
    <h1 class="card-title text-white text-4xl text-center m-2" v-if="title">{{ title }}</h1>
    <slot></slot>
  </div>
</template>

<script>
import VLink from '../components/VLink.vue'

export default {
  components: {
    VLink
  },
  props: ['title'],
  created()
  {
    this.loadVariables();
  },
  mounted()
  {
  },
  methods: {
    loadVariables: function ()
    {
      this.appName = window.__MF.appName;
    }
  },
  data: function ()
  {
    return {
      appName: null
    }
  }
}
</script>

<style src="vue-multiselect/dist/vue-multiselect.css"></style>
