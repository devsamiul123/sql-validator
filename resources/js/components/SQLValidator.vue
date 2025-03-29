<template>
    <div class="sql-validator">
        <h1 class="mb-4">SQL Query Validator</h1>
        
        <div class="card mb-4">
            <div class="card-body">
                <form @submit.prevent="validateQuery">
                    <div class="mb-3">
                        <label for="sqlQuery" class="form-label">Enter your SQL query:</label>
                        <textarea
                            v-model="sqlQuery"
                            class="form-control code-editor"
                            id="sqlQuery"
                            placeholder="SELECT * FROM users WHERE id = 1;"
                            rows="10"
                        ></textarea>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary" :disabled="store.loading">
                            <span v-if="store.loading" class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Validate Query
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div v-if="store.validationResult">
            <div
                class="card mb-4"
                :class="store.validationResult.valid ? 'text-white bg-success' : 'text-white bg-danger'"
            >
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="bi" :class="store.validationResult.valid ? 'bi-check-circle' : 'bi-exclamation-circle'"></i>
                        {{ store.validationResult.valid ? 'SQL is valid!' : 'SQL has errors' }}
                    </h4>
                </div>
                
                <div class="card-body">
                    <div v-if="!store.validationResult.valid">
                        <p class="fw-bold">Error details:</p>
                        <p>{{ store.validationResult.error }}</p>
                        <p v-if="store.validationResult.line">
                            Error at line {{ store.validationResult.line }}, word {{ store.validationResult.word }}
                        </p>
                        
                        <div class="error-highlight">
                            <pre><code>{{ highlightError() }}</code></pre>
                        </div>
                    </div>
                    
                    <div v-if="store.validationResult.valid">
                        <p>Your SQL query syntax is correct!</p>
                        <div class="collapse" id="parsedCollapse">
                            <pre class="mt-3"><code>{{ JSON.stringify(store.validationResult.parsed, null, 2) }}</code></pre>
                        </div>
                        <button class="btn btn-light btn-sm mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#parsedCollapse">
                            Show/Hide parsed structure
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useValidatorStore } from '../stores/validatorStore';

const sqlQuery = ref('');
const store = useValidatorStore();

function validateQuery() {
    if (!sqlQuery.value.trim()) {
        return;
    }
    
    store.validateSQL(sqlQuery.value);
}

function highlightError() {
    if (!store.validationResult || store.validationResult.valid) {
        return '';
    }
    
    const lines = sqlQuery.value.split('\n');
    const errorLine = store.validationResult.line;
    
    if (errorLine <= lines.length) {
        // Return a few lines before and after the error for context
        const startLine = Math.max(0, errorLine - 3);
        const endLine = Math.min(lines.length, errorLine + 2);
        
        let result = '';
        
        for (let i = startLine; i < endLine; i++) {
            let lineNum = (i + 1).toString().padStart(3, ' ');
            
            if (i + 1 === errorLine) {
                // Mark the error line
                result += `${lineNum} > ${lines[i]}\n`;
                
                // Add a marker under the error position
                if (store.validationResult.position) {
                    const marker = ' '.repeat(lineNum.length + 3 + store.validationResult.position) + '^';
                    result += `${marker}\n`;
                }
            } else {
                result += `${lineNum}   ${lines[i]}\n`;
            }
        }
        
        return result;
    }
    
    return sqlQuery.value;
}
</script>

<style>
.code-editor {
    font-family: monospace;
    font-size: 14px;
}

.error-highlight {
    background-color: rgba(255, 255, 255, 0.2);
    padding: 10px;
    border-radius: 4px;
    font-family: monospace;
    overflow-x: auto;
}
</style>