<script setup lang="ts">
import Toast from '@/Components/ui/Toast.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ devLogin: boolean }>();
</script>

<template>
    <Head title="Sign in" />
    <Toast />
    <div class="grid min-h-dvh lg:grid-cols-2">
        <!-- Brand panel -->
        <section class="relative hidden overflow-hidden bg-ink p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -left-24 -top-24 size-96 rounded-full bg-brand-orange/40 blur-3xl" aria-hidden="true" />
            <div class="absolute -bottom-32 right-0 size-[28rem] rounded-full bg-brand-magenta/30 blur-3xl" aria-hidden="true" />
            <div class="absolute bottom-24 left-24 size-64 rounded-full bg-brand-cyan/25 blur-3xl" aria-hidden="true" />
            <img src="/brand/QIU_Student_Activity_White.png" alt="QIU Student Activity" class="relative h-14 w-auto self-start" />
            <div class="relative grid gap-4">
                <h2 class="text-5xl font-bold leading-tight">Every club.<br />Every event.<br /><span class="text-gradient-warm">One campus hub.</span></h2>
                <p class="max-w-md text-white/70">Follow clubs, join events, earn CCA hours and run your club's paperwork — all with your QIU account.</p>
            </div>
            <p class="relative text-sm text-white/50">Student Life Division · Quest International University</p>
        </section>

        <!-- Sign-in -->
        <section class="relative grid place-items-center overflow-hidden px-6 py-12">
            <div class="absolute -right-24 -top-24 size-72 rounded-full bg-brand-yellow/40 blur-3xl lg:hidden" aria-hidden="true" />
            <div class="relative grid w-full max-w-sm gap-8 text-center">
                <img src="/brand/QIU_Student_Activity_Black.png" alt="QIU Student Activity" class="mx-auto h-16 w-auto" />
                <div class="grid gap-2">
                    <h1 class="text-3xl font-bold"><span class="text-gradient-warm">Campus</span> <span class="text-gradient-cool">Hub</span></h1>
                    <p class="text-ink-muted">Sign in with your <strong class="text-ink">@qiu.edu.my</strong> account.</p>
                </div>
                <a href="/auth/google/redirect" class="flex h-12 items-center justify-center gap-3 rounded-control border border-line bg-surface font-medium shadow-card transition hover:shadow-pop">
                    <svg width="20" height="20" viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z" />
                        <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z" />
                        <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2c-2 1.5-4.5 2.4-7.2 2.4-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z" />
                        <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z" />
                    </svg>
                    Continue with Google
                </a>
                <a href="/auth/google/alumni" class="text-sm text-ink-muted hover:underline">Graduated? Sign in with the personal email you linked</a>
                <Link v-if="devLogin" href="/dev/login-as" class="text-sm font-medium text-brand-blue">Local dev: log in as a demo account</Link>
            </div>
        </section>
    </div>
</template>
