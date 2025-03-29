<template>
    <div class="home">
        <div class="p-5 mb-4 bg-light rounded-3">
            <div class="container-fluid py-5">
                <h1 class="display-5 fw-bold">SQL Query Validator</h1>
                <p class="col-md-8 fs-4">Analyze and validate SQL queries using context-free grammar and lexical analysis</p>
                <p>
                    This tool helps you validate SQL queries, identify syntax errors, and shows exactly where the error occurs.
                </p>
                <router-link :to="{ name: 'validator' }" class="btn btn-primary btn-lg">
                    Try the Validator
                </router-link>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Syntax Validation</h5>
                        <p class="card-text">
                            Our parser uses context-free grammar to validate your SQL syntax and identify errors.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Error Location</h5>
                        <p class="card-text">
                            When an error is found, we show the exact line number and word position of the error.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Modern UI</h5>
                        <p class="card-text">
                            Enjoy a clean, responsive interface with syntax highlighting and real-time validation.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
// Using Vue 3 Composition API with <script setup>
</script>