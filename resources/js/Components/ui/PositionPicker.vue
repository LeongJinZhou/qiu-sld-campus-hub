<script setup lang="ts">
import { computed, ref, watch } from "vue";
import Input from "./Input.vue";
import Select from "./Select.vue";

/** Choose a committee position from the usual list (compulsory ones first), or "Other" to type your own. */
const props = withDefaults(
    defineProps<{
        label?: string;
        required?: string[];
        taken?: string[];
        error?: string;
    }>(),
    {
        label: "Role",
        required: () => [
            "President",
            "Vice President",
            "Secretary",
            "Treasurer",
        ],
        taken: () => [],
    },
);
const model = defineModel<string>({ default: "" });

const COMMON = [
    "Program Director",
    "Logistics Head",
    "Publicity Head",
    "Multimedia Head",
    "Sponsorship Head",
    "Food & Beverage Head",
    "Safety Officer",
    "Registration Head",
    "Assistant Secretary",
    "Assistant Treasurer",
    "Committee Member",
];
const OTHER = "__other__";
const options = computed(() => [
    ...props.required
        .filter((p) => !props.taken.includes(p))
        .map((p) => ({ value: p, label: `${p} (compulsory)` })),
    ...COMMON.filter((p) => !props.required.includes(p)).map((p) => ({
        value: p,
        label: p,
    })),
    { value: OTHER, label: "Other…" },
]);
const choice = ref<string | number | null>(
    model.value && !options.value.some((o) => o.value === model.value)
        ? OTHER
        : model.value || null,
);
const custom = ref(choice.value === OTHER ? model.value : "");
watch(
    [choice, custom],
    ([c, text]) => (model.value = c === OTHER ? text : String(c ?? "")),
);
watch(model, (v) => {
    if (v === "") {
        choice.value = null;
        custom.value = "";
    }
});
</script>

<template>
    <div class="grid gap-2">
        <Select
            v-model="choice"
            :label="label"
            :options="options"
            placeholder="Choose a role"
            :error="choice === OTHER ? undefined : error"
        />
        <Input
            v-if="choice === OTHER"
            v-model="custom"
            label="Role name"
            placeholder="e.g. Decoration Head"
            :error="error"
        />
    </div>
</template>
