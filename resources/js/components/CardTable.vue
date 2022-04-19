<template>
  <div class="card-table">
    <div class="card-table-header bg-dark">{{ title }}</div>
    <div class="card-table-body">
      <table v-if="Object.keys(collection).length" class="table-fixed table-responsive">
        <thead class="text-dark">
        <tr>
          <th class="w-1/4 ..." v-for="(details, key) in collection[Object.keys(collection)[0]]">
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
      <slot></slot>
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