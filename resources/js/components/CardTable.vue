<template>
  <div class="card-table">
    <div class="card-table-header">{{ title }}</div>
    <div class="card-table-body">
      <table class="table-fixed" v-if="collection.length">
        <thead class="text-dark">
        <tr>
          <th class="w-1/4 ..." v-for="(details, key) in collection[0]">
            {{ key.charAt(0).toUpperCase() + key.slice(1) }}
          </th>
        </tr>
        </thead>
        <tbody class="text-white">
        <tr v-for="item in collection">
          <td v-for="details in item">{{ details }}</td>
        </tr>
        </tbody>
      </table>
      <p v-else>No {{ itemName }} was found.</p>
    </div>
  </div>
</template>

<script>
export default {
  name: "CardTable",
  props: ['title', 'itemName', 'collection']
}
</script>

<style scoped>

table {
  width: 100%;
}

</style>