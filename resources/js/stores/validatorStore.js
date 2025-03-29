// resources/js/stores/validatorStore.js

import { defineStore } from 'pinia';
import axios from 'axios';

export const useValidatorStore = defineStore('validator', {
    state: () => ({
        validationResult: null,
        loading: false
    }),
    
    actions: {
        async validateSQL(query) {
            this.loading = true;
            try {
                const response = await axios.post('/api/validate-sql', { query });
                this.validationResult = response.data;
            } catch (error) {
                if (error.response && error.response.data) {
                    this.validationResult = error.response.data;
                } else {
                    this.validationResult = {
                        valid: false,
                        error: 'An unexpected error occurred'
                    };
                }
            } finally {
                this.loading = false;
            }
        }
    }
});