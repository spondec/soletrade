<template>
  <a :href="href" @click.prevent="go">
    <slot></slot>
  </a>
</template>

<script>

import routes from '../routes';

export default {
  props: {
    href: {
      type: String,
      required: true
    }
  },

  methods: {
    go()
    {
      this.$root.currentRoute = this.href
      window.history.pushState(null, routes[this.href], this.href)
    }
  }
}
</script>