<template>
  <main-layout title="Dashboard">
    <div class="grid xl:grid-cols-4 lg:grid-cols-3 md:grid-cols-2 sm:grid-cols-1 gap-4" v-if="!loading">
      <card-table title="Exchanges" itemName="exchange" v-bind:collection="exchanges"/>
      <card-table title="Balances" itemName="balance" v-bind:collection="balances"/>
      <card-table title="Trades" itemName="trade" v-bind:collection="trades"/>
      <card-table title="Active Strategies" itemName="strategy" v-bind:collection="strategies"/>
    </div>
    <v-spinner v-else/>
  </main-layout>
</template>

<script>

import CardTable from "../components/CardTable";
import VLink from "../components/VLink";
import MainLayout from "../layouts/Main";
import ApiService from "../services/ApiService";
import VSpinner from "../components/VSpinner";

export default {
  title: 'Dashboard',
  components: {VSpinner, MainLayout, VLink, CardTable},
  data: function ()
  {
    return {
      loading: true,
      exchanges: [],
      balances: [],
      strategies: [],
      trades: []
    }
  },

  created()
  {
    this.load();
  },
  mounted()
  {

  },
  methods: {
    load: async function ()
    {
      this.exchanges = await ApiService.exchanges();
      this.balances = await ApiService.balances();
      this.trades = await ApiService.trades();

      this.loading = false;
    },
  }
}
</script>

<style scoped>

</style>
