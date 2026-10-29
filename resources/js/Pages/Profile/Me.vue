<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import Input from '@/Components/ui/Input.vue';
import QrCode from '@/Components/ui/QrCode.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { ProfileData } from '@/Pages/Profile/Public.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    profile: ProfileData;
    account: {
        email: string;
        student_number: string | null;
        status: string | null;
        has_photo_override: boolean;
        personal_email: string | null;
        hidden_sections: string[];
    };
    hideableSections: string[];
    publicUrl: string;
    qrSvg: string;
}>();

const form = useForm({
    photo: [] as File[],
    remove_photo: false,
    hidden_sections: [...props.account.hidden_sections],
    personal_email: props.account.personal_email ?? '',
});

const visible = (section: string) =>
    computed({
        get: () => !form.hidden_sections.includes(section),
        set: (show: boolean) => {
            form.hidden_sections = show ? form.hidden_sections.filter((s) => s !== section) : [...form.hidden_sections, section];
        },
    });
const sectionToggles = props.hideableSections.map((section) => ({ section, model: visible(section) }));

function save(): void {
    form.transform((data) => ({ ...data, photo: data.photo[0] ?? null })).post('/me', {
        preserveScroll: true,
        onSuccess: () => form.reset('photo', 'remove_photo'),
    });
}

const logout = (): void => router.post('/logout');
</script>

<template>
    <Head title="My profile" />
    <AppLayout>
        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="grid gap-6">
                <header class="flex flex-wrap items-center gap-5">
                    <Avatar :src="profile.photo_url" :name="profile.name" size="xl" ring />
                    <div class="grid gap-1">
                        <h1 class="text-xl font-bold">{{ profile.name }}</h1>
                        <p class="text-sm text-ink-muted">{{ [profile.programme_short, profile.year_group, profile.title].filter(Boolean).join(' · ') }}</p>
                        <p class="text-xs text-ink-muted">{{ account.student_number }} · {{ account.email }}</p>
                        <Badge v-if="account.status" :tone="account.status === 'active' ? 'success' : 'neutral'" class="justify-self-start">{{ account.status }}</Badge>
                    </div>
                </header>

                <Card>
                    <template #title>Positions</template>
                    <ul v-if="profile.positions?.length" class="grid gap-2 text-sm">
                        <li v-for="(item, index) in profile.positions" :key="index" class="flex justify-between gap-4">
                            <span><strong>{{ item.position }}</strong> · {{ item.club }}</span>
                            <span class="text-ink-muted">{{ item.session }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-ink-muted">No positions yet.</p>
                </Card>

                <Card>
                    <template #title>Edit profile</template>
                    <form class="grid gap-5" @submit.prevent="save">
                        <FileUpload v-model="form.photo" label="Profile photo (replaces your Google photo)" accept="image/jpeg,image/png,image/webp" :error="form.errors.photo" />
                        <Checkbox v-if="account.has_photo_override" v-model="form.remove_photo" label="Go back to my Google photo" />

                        <fieldset class="grid gap-2">
                            <legend class="mb-1 text-sm font-medium">Show on public profile</legend>
                            <Checkbox v-for="toggle in sectionToggles" :key="toggle.section" v-model="toggle.model.value" :label="toggle.section" class="capitalize" />
                        </fieldset>

                        <Input
                            v-model="form.personal_email"
                            type="email"
                            label="Personal email"
                            hint="Lets you keep signing in as alumni after graduation."
                            :error="form.errors.personal_email"
                        />
                        <Button type="submit" :loading="form.processing" class="justify-self-start">Save</Button>
                    </form>
                </Card>
            </div>

            <aside class="grid content-start gap-6">
                <Card>
                    <template #title>My QR</template>
                    <QrCode :svg="qrSvg" label="Anyone who scans sees your public profile" download-name="my-qiu-profile" />
                    <a :href="publicUrl" class="mt-3 block text-center text-sm font-medium text-brand-blue">View public profile</a>
                </Card>
                <Button variant="secondary" @click="logout">Sign out</Button>
            </aside>
        </div>
    </AppLayout>
</template>
