<template>
  <main-layout>
    <h1 class="card-title text-white text-4xl text-center m-2">Dashboard</h1>
    <div class="grid 2xl:grid-cols-5 xl:grid-cols-4 lg:grid-cols-3 md:grid-cols-2 sm:grid-cols-1 gap-4">
      <card-table title="Exchanges" itemName="exchange" v-bind:collection="exchanges"/>
      <card-table title="Active Trades" itemName="active trade" v-bind:collection="trades.active"/>
      <card-table title="Recent Trades" itemName="recent trade" v-bind:collection="trades.recent"/>
      <card-table title="Active Strategies" itemName="strategy" v-bind:collection="strategies"/>
      <card-table title="Balances" itemName="balance" v-bind:collection="balances"/>
    </div>
  </main-layout>
</template>

<script>

import CardTable from "../components/CardTable";
import VLink from "../components/VLink";
import MainLayout from "../layouts/Main";

export default {
  title: 'Dashboard',
  components: {MainLayout, VLink, CardTable},
  data: function ()
  {
    return {
      exchanges: [],
      balances: [],
      strategies: [],
      trades: {
        recent: [],
        active: []
      }
    }
  },
  mounted()
  {
    this.load();
  },
  methods: {
    load: function ()
    {
      this.get('api/exchanges/', data => this.exchanges = data);
      // this.get('api/exchanges/balances', [this, 'balances']);
      //   this.get('api/trades/active', [this, 'activeTra']);
      //   this.get('api/trades/recent', [this, 'recentTrades']);
      //   this.get('api/strategies/', [this, 'strategies']);
    },
    get: function (url, callback)
    {
      axios.get(url)
          .then(response =>
          {
            console.log(response)
            callback(response.data)
          })
          .catch(error =>
          {
            console.log(error)
            alert('Error')
          });
    }
  }
}
</script>

<style scoped>

</style>
