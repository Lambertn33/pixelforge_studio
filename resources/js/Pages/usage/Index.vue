<script setup lang="ts">
import Header from '@/components/usage/Header.vue';
import DayTable from '@/components/usage/DayTable.vue';
import Summary from '@/components/usage/Summary.vue';
import Filters from '@/components/usage/Filters.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { UsageReport } from '@/types/usage';
import { Head } from '@inertiajs/vue3';

defineOptions({
    layout: AppLayout,
});

defineProps<{
    report: UsageReport;
    filters: {
        start_date: string;
        end_date: string;
    };
}>();
</script>

<template>
    <Head title="Usage" />

    <Header />

    <Filters :start-date="filters.start_date" :end-date="filters.end_date" />

    <Summary :report="report" />

    <DayTable :days="report.days" />
</template>
