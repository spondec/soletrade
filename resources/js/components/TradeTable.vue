<template>
  <table v-if="paginated.length" class="table table-responsive text-white">
    <thead>
    <tr>
      <th>Magnifier</th>
      <th>Details</th>
      <th>Side</th>
      <th>Entry</th>
      <th>Exit</th>
      <th>Entry Signal</th>
      <th>Entry Date</th>
      <th>Exit Signal</th>
      <th>Exit Date</th>
      <th>Entry Price</th>
      <th>Exit Price</th>
      <th>Target Price</th>
      <th>Stop Price</th>
      <th>ROI %</th>
      <th>Highest ROI %</th>
      <th>Lowest ROI %</th>
    </tr>
    </thead>
    <tbody>

    <tr v-for="trade in paginated" class="bg-opacity-50" v-bind:class="{
        'bg-danger': trade.relative_roi < 0,
        'bg-success': trade.relative_roi > 0,
        'bg-warning' : !trade.is_entry_price_valid,
        'bg-info': trade.is_ambiguous
      }">
      <td>
        <button type="button"
                v-on:click="magnify(trade)">
          Magnify
        </button>
      </td>
      <td>
        <vue-json-pretty :path="'res'" :data="trade" :deep="0"></vue-json-pretty>
      </td>
      <td>{{ trade.entry.side }}</td>
      <td>{{ trade.entry.name }}</td>
      <td>{{ trade.exit.name }}</td>
      <td>
        <a v-bind:href="'#' + chartId"
           v-on:click="dateClick(trade.entry.timestamp, trade.exit.timestamp)">
          {{ timestampToString(trade.entry.price_date) }}
        </a>
      </td>
      <td>
        <a v-if="trade.entry_timestamp" v-bind:href="'#' + chartId"
           v-on:click="dateClick(trade.entry_timestamp, trade.exit_timestamp)">
          {{ timestampToString(trade.entry_timestamp) }}
        </a>
        <p v-else>N/A</p>
      </td>
      <td>
        <a v-bind:href="'#' + chartId"
           v-on:click="dateClick(trade.entry.timestamp, trade.exit.timestamp)">
          {{ timestampToString(trade.exit.price_date) }}
        </a>
      </td>
      <td>
        <a v-if="trade.entry_timestamp" v-bind:href="'#' + chartId"
           v-on:click="dateClick(trade.entry_timestamp, trade.exit_timestamp)">
          {{ timestampToString(trade.exit_timestamp) }}
        </a>
        <p v-else>N/A</p>
      </td>
      <td v-bind:class="{ 'text-danger': !trade.is_entry_price_valid }">{{ round(trade.entry_price) }}</td>
      <td>{{ round(trade.exit_price) }}</td>
      <td>
        <p v-bind:class="{'text-warning': trade.is_closed }">
          {{ round(trade.target_price) || 'N/A' }}</p>
      </td>
      <td>
        <p v-bind:class="{'text-warning': trade.is_stopped }">
          {{ round(trade.stop_price) || 'N/A' }}</p>
      </td>
      <td>{{ round(trade.relative_roi) || 'N/A' }}</td>
      <td>{{ round(trade.highest_roi) || 'N/A' }}</td>
      <td>{{ round(trade.lowest_roi) || 'N/A' }}</td>
    </tr>
    </tbody>
  </table>
  <pagination v-model=" page" :per-page="perPage" :records="trades.length" @paginate="paginate"/>
</template>

<script>

import Pagination from 'v-pagination-3';

import VueJsonPretty from 'vue-json-pretty';
import 'vue-json-pretty/lib/styles.css';

export default {
  name: "TradeTable",
  props: ['trades', 'chartId'],
  emits: ['dateClick', 'magnify'],
  components: {Pagination, VueJsonPretty},

  data: function ()
  {
    return {
      paginated: [],
      perPage: 5,
      page: 1
    }
  },

  created()
  {
    this.paginate(this.page);
  },

  methods: {

    magnify: function (trade)
    {
      this.$emit('magnify', trade);
    },

    dateClick: function (a, b)
    {
      if (a && b)
        this.$emit('dateClick', a, b);
      else if (a)
        this.$emit('dateClick', a, a);
      else if (b)
        this.$emit('dateClick', b, b);
    },
    paginate: function (page)
    {
      const start = (page - 1) * this.perPage;
      const end = start + this.perPage;
      this.paginated = this.$props.trades.slice(start, end);
    },

    round: function (float)
    {
      const parsed = parseFloat(float);

      return !Number.isNaN(parsed) ? parsed.toFixed(2) : float;
    },

    timestampToString: function (timestamp)
    {
      if (!timestamp) return 'N/A';
      try
      {
        return new Date(timestamp).toISOString().slice(0, 19).replace('T', ' ');
      } catch (e)
      {
        return 'N/A';
      }
    }
  }
}
</script>

<style scoped>

</style>