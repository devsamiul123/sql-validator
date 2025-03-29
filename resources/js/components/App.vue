<template>
    <div class="app-container">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="#">SQL Validator</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <router-link class="nav-link" :to="{ name: 'home' }">Home</router-link>
                        </li>
                        <li class="nav-item">
                            <router-link class="nav-link" :to="{ name: 'validator' }">Validator</router-link>
                        </li>
                    </ul>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="https://github.com/yourusername/sql-validator" target="_blank">
                                <i class="bi bi-github"></i> GitHub
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-4">
            <div class="container">
                <router-view></router-view>
            </div>
        </main>

        <footer class="py-3 bg-light">
            <div class="container">
                <p class="text-center mb-0">
                    SQL Validator &copy; {{ new Date().getFullYear() }}
                </p>
            </div>
        </footer>
    </div>
</template>

<script setup>
// Using Vue 3 Composition API with <script setup>
</script>

<style>
.app-container {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

main {
    flex: 1;
}
</style>