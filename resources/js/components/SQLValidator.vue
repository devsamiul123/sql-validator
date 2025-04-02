<template>
    <div class="sql-validator-container">
      <div class="header">
        <h1>SQL Validator</h1>
        <p>Enter your SQL query to validate syntax</p>
      </div>
      
      <div class="sql-input-container">
        <textarea
          v-model="sql"
          class="sql-textarea"
          placeholder="Enter your SQL query here..."
          :class="{ 'error-highlight': hasErrors }"
          @input="clearResults"
        ></textarea>
        
        <button 
          @click="validateSql" 
          class="validate-button"
          :disabled="isLoading || !sql.trim()"
        >
          <span v-if="isLoading" class="loading-spinner"></span>
          <span v-else>Validate SQL</span>
        </button>
  
        <button 
          v-if="sql.trim() && !isLoading"
          @click="saveToHistory" 
          class="save-button"
          :disabled="!sql.trim()"
        >
          Save Query
        </button>
      </div>
      
      <transition name="fade">
        <div v-if="result" class="result-container">
          <div v-if="result.isValid" class="valid-result">
            <div class="valid-icon">✓</div>
            <p>SQL query is valid!</p>
          </div>
          
          <div v-else class="error-result">
            <h2>Validation Errors</h2>
            <div v-for="(error, index) in result.errors" :key="index" class="error-card">
              <div class="error-header">
                <span class="error-badge">Line {{ error.line }}</span>
                <span class="error-word">{{ error.word }}</span>
              </div>
              <div class="error-message">{{ error.message }}</div>
              <pre class="error-line-content">{{ error.lineContent }}</pre>
            </div>
          </div>
        </div>
      </transition>
  
      <div v-if="historyStore.queries.length > 0" class="history-container">
        <h2>Query History</h2>
        <div v-for="(query, index) in historyStore.queries" :key="index" class="history-item">
          <div class="history-query" @click="loadFromHistory(query)">
            {{ query.text.length > 50 ? query.text.substring(0, 50) + '...' : query.text }}
          </div>
          <div class="history-date">{{ new Date(query.timestamp).toLocaleString() }}</div>
          <div class="history-status" :class="query.isValid ? 'valid' : 'invalid'">
            {{ query.isValid ? 'Valid' : 'Invalid' }}
          </div>
          <button class="delete-button" @click.stop="deleteFromHistory(index)">×</button>
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, computed } from 'vue';
  import axios from 'axios';
  import { useHistoryStore } from '../stores/history';
  
  const sql = ref('');
  const result = ref(null);
  const isLoading = ref(false);
  const hasErrors = computed(() => result.value && !result.value.isValid);
  const historyStore = useHistoryStore();
  
  const validateSql = async () => {
    if (!sql.value.trim()) return;
    
    isLoading.value = true;
    try {
      const response = await axios.post('/api/validate-sql', {
        sql: sql.value
      });
      
      result.value = response.data;
    } catch (error) {
      console.error('Error validating SQL:', error);
      result.value = {
        isValid: false,
        errors: [{ message: 'Server error occurred. Please try again later.', line: 0, word: '', lineContent: '' }]
      };
    } finally {
      isLoading.value = false;
    }
  };
  
  const clearResults = () => {
    if (result.value) {
      result.value = null;
    }
  };
  
  const saveToHistory = () => {
    if (!sql.value.trim()) return;
    
    historyStore.addQuery({
      text: sql.value,
      timestamp: new Date().toISOString(),
      isValid: result.value ? result.value.isValid : false
    });
  };
  
  const loadFromHistory = (query) => {
    sql.value = query.text;
    clearResults();
  };

  const deleteFromHistory = (index) => {
    historyStore.removeQuery(index);
  };
  </script>
  