<template>
  <main-layout title="Dashboard">
    <div class="container m-auto grid m-3 xl:grid-cols-2 lg:grid-cols-2 md:grid-cols-2 sm:grid-cols-1 gap-4">
      <card-table title="Exchanges" itemName="exchange" v-bind:collection="exchanges"/>
      <card-table itemName="balance" title="Balances" v-bind:collection="balances"/>
      <card-table title="Trades" itemName="trade" v-bind:collection="trades"/>
      <card-table itemName="strategy" title="Strategies" v-bind:collection="strategies"/>
    </div>
  </main-layout>
</template>

<script>

import CardTable from "../components/CardTable";
import MainLayout from "../layouts/Main";
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
      this.trades = trades.map(trade =>
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
