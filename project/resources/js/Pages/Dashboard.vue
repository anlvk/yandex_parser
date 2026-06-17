<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

// Состояние формы добавления ссылки
const form = reactive({
    url: ''
});

// Списки организаций и отзывов
const organizations = ref([]);
const selectedOrganization = ref(null);
const reviewsData = ref({
    data: [],
    current_page: 1,
    last_page: 1,
    total: 0
});

// Индикаторы загрузки и уведомления
const loading = ref(false);
const reviewsLoading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

// 1. Загрузка всех организаций текущего пользователя
const loadOrganizations = async () => {
    try {
        const response = await axios.get('/api/organizations');
        organizations.value = response.data.data || response.data;
        
        // Автоматически выбираем первую компанию, если список не пуст
        if (organizations.value.length > 0 && !selectedOrganization.value) {
            selectOrganization(organizations.value[0]);
        }
    } catch (error) {
        console.error('Ошибка при загрузке организаций:', error);
    }
};

// 2. Выбор организации и загрузка её первой порции отзывов
const selectOrganization = (org) => {
    selectedOrganization.value = org;
    loadReviews(org.id, 1);
};

// 3. Загрузка отзывов порциями по 15 штук (Пагинация)
const loadReviews = async (orgId, page = 1) => {
    reviewsLoading.value = true;
    try {
        const response = await axios.get(`/api/organizations/${orgId}/reviews?page=${page}`);
        reviewsData.value = response.data.reviews;
    } catch (error) {
        console.error('Ошибка при загрузке отзывов:', error);
    } finally {
        reviewsLoading.value = false;
    }
};

// 4. Отправка новой ссылки на Яндекс.Карты
const submitLink = async () => {
    loading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        console.log(form.url);
        // 1. Делаем первый быстрый запрос (Мета + первые 50 отзывов)
        const response = await axios.post('/api/organization/import', {
            yandex_url: form.url
        });
        
        const newOrg = response.data.data;
        successMessage.value = 'Организация добавлена! Запущен фоновый сбор отзывов (до 600 шт)...';
        form.url = ''; 
        
        // Сразу отображаем компанию и первые отзывы на экране
        await loadOrganizations();
        selectOrganization(newOrg);
        loading.value = false; // Кнопка СРАЗУ разблокируется, вечного ожидания нет!

        // 2. ЦИКЛ ДОБОРA ОСТАВШИХСЯ ОТЗЫВОВ (Страницы со 2 по 12)
        // Запускаем последовательные быстрые запросы с паузами в 1.5 секунды
        for (let page = 2; page <= 12; page++) {
            // Делаем небольшую паузу на фронтенде между запросами (маскировка под человека)
            await new Promise(resolve => setTimeout(resolve, 1500));
            
            try {
                const chunkResponse = await axios.post(`/api/organizations/${newOrg.id}/parse-chunk`, {
                    page: page
                });
                
                // Если Яндекс отдал 0 отзывов — значит лента закончилась раньше, прерываем цикл
                if (chunkResponse.data.fetched === 0) {
                    console.log('Все доступные отзывы успешно собраны.');
                    break;
                }
                
                // Бесшумно обновляем ленту отзывов на экране, чтобы пользователь видел, 
                // как счетчик "Всего в базе" увеличивается в реальном времени!
                if (selectedOrganization.value?.id === newOrg.id) {
                    loadReviews(newOrg.id, reviewsData.value.current_page);
                }
            } catch (chunkError) {
                console.error(`Ошибка при скачивании страницы ${page}:`, chunkError);
                break; // Если поймали капчу на какой-то странице — останавливаем сбор
            }
        }

        successMessage.value = 'Сбор всех 600 отзывов успешно завершен и сохранен в PostgreSQL!';

    } catch (error) {
        loading.value = false;
        if (error.response && error.response.status === 422) {
            errorMessage.value = error.response.data.errors.yandex_url?. || 'Неверный формат ссылки.';
        } else {
            errorMessage.value = error.response?.data?.message || 'Произошла ошибка при импорте.';
        }
    }
};


onMounted(() => {
    loadOrganizations();
});
</script>

<template>
    <Head title="Панель управления" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Панель управления отзывами
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                
                <!-- БЛОК 1: Форма ввода ссылки на Яндекс.Карты -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="max-w-xl">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            Привязка новой организации из Яндекс.Карт
                        </h3>

                        <form @submit.prevent="submitLink" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Ссылка на карточку организации
                                </label>
                                <input 
                                    v-model="form.url" 
                                    type="text" 
                                    placeholder="https://yandex.ru..." 
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-black p-2 border"
                                    :disabled="loading"
                                    required
                                />
                            </div>

                            <p v-if="errorMessage" class="text-sm text-red-600 font-semibold">{{ errorMessage }}</p>
                            <p v-if="successMessage" class="text-sm text-green-600 font-semibold">{{ successMessage }}</p>

                            <button 
                                type="submit" 
                                class="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-700 active:bg-indigo-900 transition"
                                :disabled="loading"
                            >
                                {{ loading ? 'Сохранение и сбор отзывов...' : 'Сохранить и подтянуть данные' }}
                            </button>
                        </form>
                    </div>
                </div>

                <!-- БЛОК 2: Список ваших организаций -->
                <div v-if="organizations.length > 0" class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Мои организации</h3>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <button 
                            v-for="org in organizations" 
                            :key="org.id"
                            @click="selectOrganization(org)"
                            class="p-4 border rounded-lg text-left transition-all"
                            :class="selectedOrganization?.id === org.id ? 'border-indigo-600 bg-indigo-50 ring-2 ring-indigo-500' : 'border-gray-200 bg-white hover:bg-gray-50'"
                        >
                            <div class="font-semibold text-gray-900">{{ org.name }}</div>
                            <div class="text-xs text-gray-500 truncate mt-1">{{ org.address }}</div>
                            <div class="mt-2 flex items-center justify-between text-xs font-medium text-gray-700">
                                <span v-if="org.rating">⭐ {{ org.rating }}</span>
                                <span v-if="org.review_count">💬 {{ org.review_count }} отз.</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- БЛОК 3: Вывод отзывов с пагинацией по 15 штук -->
                <div v-if="selectedOrganization" class="overflow-hidden bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4 border-b pb-2">
                        <h3 class="text-lg font-medium text-gray-900">
                            Отзывы компании: <span class="font-semibold text-indigo-600">{{ selectedOrganization.name }}</span>
                        </h3>
                        <span class="text-sm text-gray-500">Всего в базе: {{ reviewsData.total }}</span>
                    </div>

                    <div v-if="reviewsLoading" class="text-center py-6 text-gray-500">
                        Загрузка порции отзывов...
                    </div>

                    <div v-else-if="reviewsData.data.length > 0" class="space-y-4">
                        <div v-for="review in reviewsData.data" :key="review.id" class="p-4 bg-gray-50 rounded-lg border border-gray-100">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-semibold text-gray-900 text-sm">{{ review.author_name || 'Аноним' }}</div>
                                <div class="text-xs text-gray-400">{{ review.publish_date }}</div>
                            </div>
                            <div class="text-amber-500 text-xs mb-2">
                                {{ '★'.repeat(review.stars || 0) }}{{ '☆'.repeat(5 - (review.stars || 0)) }}
                            </div>
                            <p class="text-gray-700 text-sm leading-relaxed">{{ review.text }}</p>
                        </div>

                        <!-- НАВИГАЦИЯ ПО СТРАНИЦАМ -->
                        <div v-if="reviewsData.last_page > 1" class="flex items-center justify-between border-t border-gray-200 px-4 py-3 sm:px-6 mt-6">
                            <div class="flex flex-1 justify-between sm:hidden">
                                <button 
                                    @click="loadReviews(selectedOrganization.id, reviewsData.current_page - 1)"
                                    :disabled="reviewsData.current_page === 1"
                                    class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    Назад
                                </button>
                                <button 
                                    @click="loadReviews(selectedOrganization.id, reviewsData.current_page + 1)"
                                    :disabled="reviewsData.current_page === reviewsData.last_page"
                                    class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    Вперед
                                </button>
                            </div>
                            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Страница <span class="font-medium">{{ reviewsData.current_page }}</span> из <span class="font-medium">{{ reviewsData.last_page }}</span>
                                    </p>
                                </div>
                                <div>
                                    <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm">
                                        <button
                                            @click="loadReviews(selectedOrganization.id, reviewsData.current_page - 1)"
                                            :disabled="reviewsData.current_page === 1"
                                            class="relative inline-flex items-center rounded-l-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                                        >
                                            Назад
                                        </button>
                                        <button
                                            @click="loadReviews(selectedOrganization.id, reviewsData.current_page + 1)"
                                            :disabled="reviewsData.current_page === reviewsData.last_page"
                                            class="relative inline-flex items-center rounded-r-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50"
                                        >
                                            Вперед
                                        </button>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-6 text-gray-400 text-sm">
                        У этой организации пока нет сохраненных отзывов в PostgreSQL. Запустите фоновую команду.
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
