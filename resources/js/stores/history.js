import { defineStore } from 'pinia';

export const useHistoryStore = defineStore('history', {
  state: () => ({
    queries: []
  }),
  
  actions: {
    addQuery(query) {
      // Add to the beginning of the array
      this.queries.unshift(query);
      
      // Keep only the last 10 queries
      if (this.queries.length > 10) {
        this.queries.pop();
      }
    },
    removeQuery(index) {
      this.queries.splice(index, 1);
    },
    
    clearHistory() {
      this.queries = [];
    }
  },
  
  persist: {
    enabled: true,
    strategies: [
      {
        key: 'sql-validator-history',
        storage: localStorage
      }
    ]
  }
});
