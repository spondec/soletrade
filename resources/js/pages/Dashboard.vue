<template>
  <main-layout title="Dashboard">
    <div class="container m-auto grid m-3 xl:grid-cols-4 lg:grid-cols-3 md:grid-cols-2 sm:grid-cols-1 gap-4">
      <card-table itemName="exchange" title="Active Exchanges" v-bind:collection="exchanges"/>
      <card-table itemName="balance" title="Balances" v-bind:collection="balances"/>
      <card-table itemName="trade" title="Recent Trades" v-bind:collection="trades"/>
      <card-table itemName="strategy" title="Strategies" v-bind:collection="strategies"/>
    </div>
  </main-layout>
</template>

<script>

import CardTable from "../components/CardTable.vue";
import MainLayout from "../layouts/Main.vue";
import ApiService from "../services/ApiService";

export default {
  title: 'Dashboard',
  components: {MainLayout, CardTable},
  data: function ()
  {
    return {
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
      this.strategies = await ApiService.strategies();
      const trades = await ApiService.recentTrades();
      this.trades = trades.data.map(trade =>
      {
        return {
          Symbol: trade.entry.symbol.symbol,
          Side: trade.side,
          Name: trade.entry.name,
          ROI: trade.roi + '%'
        }
      });

      this.balances = await ApiService.balances();
    },
  }
}
</script>

<style scoped>

</style>
