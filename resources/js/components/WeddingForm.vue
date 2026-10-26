<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AccountSummary, WeddingDetails } from '@/types';

const props = defineProps<{
    account: AccountSummary | null;
    wedding?: WeddingDetails;
    onboarding?: boolean;
}>();

const editing = Boolean(props.wedding);
const form = useForm({
    partner_one_name: props.wedding?.partner_one_name ?? '',
    partner_two_name: props.wedding?.partner_two_name ?? '',
    wedding_date: props.wedding?.wedding_date ?? '',
    wedding_time: props.wedding?.wedding_time ?? '',
    ceremony_venue: props.wedding?.ceremony_venue ?? '',
    reception_venue: props.wedding?.reception_venue ?? '',
    ...(props.wedding?.timezone ? { timezone: props.wedding.timezone } : {}),
});

const submit = () => {
    if (editing && props.account && props.wedding) {
        form.put(route('accounts.weddings.update', { account: props.account.id, wedding: props.wedding.id }));
        return;
    }

    if (props.account) {
        form.post(route('accounts.weddings.store', { account: props.account.id }));
        return;
    }

    form.post(route('weddings.store'));
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-6">
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="partner_one_name">Partner 1 name</Label>
                <Input id="partner_one_name" v-model="form.partner_one_name" autocomplete="name" required maxlength="255" />
                <InputError :message="form.errors.partner_one_name" />
            </div>

            <div class="grid gap-2">
                <Label for="partner_two_name">Partner 2 name</Label>
                <Input id="partner_two_name" v-model="form.partner_two_name" autocomplete="off" required maxlength="255" />
                <InputError :message="form.errors.partner_two_name" />
            </div>

            <div class="grid gap-2">
                <Label for="wedding_date">Wedding date <span class="font-normal text-muted-foreground">(optional)</span></Label>
                <Input id="wedding_date" v-model="form.wedding_date" type="date" />
                <InputError :message="form.errors.wedding_date" />
            </div>

            <div class="grid gap-2">
                <Label for="wedding_time">Wedding time <span class="font-normal text-muted-foreground">(optional)</span></Label>
                <Input id="wedding_time" v-model="form.wedding_time" type="time" />
                <InputError :message="form.errors.wedding_time" />
            </div>

            <div v-if="wedding?.timezone" class="grid gap-2 sm:col-span-2">
                <Label for="timezone">Wedding timezone</Label>
                <Input id="timezone" v-model="form.timezone" list="wedding-timezones" required maxlength="64" />
                <datalist id="wedding-timezones">
                    <option value="Asia/Manila" />
                    <option value="Asia/Singapore" />
                    <option value="UTC" />
                    <option value="America/New_York" />
                </datalist>
                <p class="text-xs text-muted-foreground">
                    Use the venue's IANA timezone, such as Asia/Manila. This does not change your saved date or time.
                </p>
                <InputError :message="form.errors.timezone" />
            </div>
            <div class="grid gap-2">
                <Label for="ceremony_venue">Ceremony venue <span class="font-normal text-muted-foreground">(optional)</span></Label>
                <Input id="ceremony_venue" v-model="form.ceremony_venue" maxlength="500" />
                <InputError :message="form.errors.ceremony_venue" />
            </div>

            <div class="grid gap-2">
                <Label for="reception_venue">Reception venue <span class="font-normal text-muted-foreground">(optional)</span></Label>
                <Input id="reception_venue" v-model="form.reception_venue" maxlength="500" />
                <InputError :message="form.errors.reception_venue" />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <Button type="submit" :disabled="form.processing">{{ editing ? 'Save changes' : 'Create wedding' }}</Button>
            <Link
                v-if="!onboarding"
                :href="account && wedding ? route('accounts.weddings.show', { account: account.id, wedding: wedding.id }) : route('dashboard')"
                class="text-sm text-muted-foreground underline-offset-4 hover:underline"
            >
                Cancel
            </Link>
        </div>
    </form>
</template>
