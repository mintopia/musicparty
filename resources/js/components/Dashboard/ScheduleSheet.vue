<script setup lang="ts">
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';

interface Ymd {
    y: number;
    m: number;
    d: number;
}

const props = defineProps<{
    title: string;
    href: string;
    timezone: string;
    scheduledFor?: string | null;
}>();
const emit = defineEmits<{close: []}>();

const pad = (n: number): string => String(n).padStart(2, '0');
const key = (v: Ymd): string => `${v.y}-${pad(v.m)}-${pad(v.d)}`;

function partsIn(date: Date): Ymd & {hh: number; mm: number} {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-GB', {
            timeZone: props.timezone,
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            hourCycle: 'h23',
        })
            .formatToParts(date)
            .map((p) => [p.type, Number(p.value)]),
    ) as Record<string, number>;
    return {y: parts.year, m: parts.month, d: parts.day, hh: parts.hour, mm: parts.minute};
}

function plusMinutes(minutes: number): Date {
    return new Date(Date.now() + minutes * 60_000);
}

const today = partsIn(new Date());
const initial = partsIn(props.scheduledFor ? new Date(props.scheduledFor) : plusMinutes(15));

const selected = ref<Ymd>({y: initial.y, m: initial.m, d: initial.d});
const month = ref({y: initial.y, m: initial.m});
const time = ref(`${pad(initial.hh)}:${pad(initial.mm)}`);

const monthLabel = computed(() =>
    new Intl.DateTimeFormat(undefined, {month: 'long', year: 'numeric', timeZone: 'UTC'}).format(
        new Date(Date.UTC(month.value.y, month.value.m - 1, 1)),
    ),
);

const weekdays = computed(() =>
    Array.from({length: 7}, (_, i) =>
        new Intl.DateTimeFormat(undefined, {weekday: 'short', timeZone: 'UTC'})
            .format(new Date(Date.UTC(2024, 0, 1 + i)))
            .slice(0, 2),
    ),
);

const days = computed(() => {
    const first = new Date(Date.UTC(month.value.y, month.value.m - 1, 1));
    const lead = (first.getUTCDay() + 6) % 7;
    const count = new Date(Date.UTC(month.value.y, month.value.m, 0)).getUTCDate();
    const cells = Math.ceil((lead + count) / 7) * 7;
    const longDay = new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'UTC',
    });
    return Array.from({length: cells}, (_, i) => {
        const date = new Date(Date.UTC(month.value.y, month.value.m - 1, 1 - lead + i));
        const value = {y: date.getUTCFullYear(), m: date.getUTCMonth() + 1, d: date.getUTCDate()};
        return {
            value,
            label: longDay.format(date),
            out: value.m !== month.value.m,
            past: key(value) < key(today),
            selected: key(value) === key(selected.value),
        };
    });
});

const canGoBack = computed(() => key({...month.value, d: 1}) > key({...today, d: 1}));
const validTime = computed(() => /^([01]\d|2[0-3]):[0-5]\d$/.test(time.value));
const scheduledInput = computed(() => `${key(selected.value)}T${time.value}`);
const isToday = computed(() => key(selected.value) === key(today));
const summaryDay = computed(() =>
    isToday.value
        ? 'today'
        : new Intl.DateTimeFormat(undefined, {weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC'}).format(
              new Date(Date.UTC(selected.value.y, selected.value.m - 1, selected.value.d)),
          ),
);
const submitHref = computed(() => `${props.href}?scheduled_for=${encodeURIComponent(scheduledInput.value)}`);

const chips = computed(() => {
    const soon = partsIn(plusMinutes(15));
    const half = partsIn(plusMinutes(30));
    const nextHour = partsIn(plusMinutes(60 - today.mm));
    return [
        {label: 'In 15 minutes', at: soon},
        {label: 'In 30 minutes', at: half},
        {label: `${pad(nextHour.hh)}:00`, at: nextHour},
    ];
});

function pick(value: Ymd): void {
    selected.value = value;
    month.value = {y: value.y, m: value.m};
}

function pickChip(at: Ymd & {hh: number; mm: number}): void {
    pick({y: at.y, m: at.m, d: at.d});
    time.value = `${pad(at.hh)}:${pad(at.mm)}`;
}

function shiftMonth(delta: number): void {
    const date = new Date(Date.UTC(month.value.y, month.value.m - 1 + delta, 1));
    month.value = {y: date.getUTCFullYear(), m: date.getUTCMonth() + 1};
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        emit('close');
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="sheet-dim d-md-none" data-test="schedule-dim" @click="emit('close')"></div>
    <div
        class="sched-sheet d-md-none"
        role="dialog"
        aria-modal="true"
        aria-labelledby="sched-title"
        data-test="schedule-sheet"
    >
        <button type="button" class="sched-grab" aria-label="Close" data-test="schedule-handle" @click="emit('close')">
            <span></span>
        </button>
        <div class="sched-head">
            <h2 id="sched-title">{{ title }}</h2>
            <button type="button" class="btn-close" aria-label="Close" @click="emit('close')"></button>
        </div>
        <p class="sched-hint">
            Plays as soon as possible after the time you pick. Until then it keeps its place in the Queue.
        </p>
        <div role="group" aria-label="Pick a day">
            <div class="cal-nav">
                <button
                    type="button"
                    class="btn btn-icon btn-ghost-secondary"
                    aria-label="Previous month"
                    :disabled="!canGoBack"
                    @click="shiftMonth(-1)"
                >
                    <i class="ti ti-chevron-left"></i>
                </button>
                <b>{{ monthLabel }}</b>
                <button
                    type="button"
                    class="btn btn-icon btn-ghost-secondary"
                    aria-label="Next month"
                    @click="shiftMonth(1)"
                >
                    <i class="ti ti-chevron-right"></i>
                </button>
            </div>
            <div class="cal-grid cal-wk" aria-hidden="true">
                <span v-for="(name, i) in weekdays" :key="i">{{ name }}</span>
            </div>
            <div class="cal-grid">
                <button
                    v-for="day in days"
                    :key="key(day.value)"
                    type="button"
                    class="cal-day"
                    :class="{'is-out': day.out, 'is-sel': day.selected}"
                    :aria-label="day.label"
                    :aria-pressed="day.selected"
                    :disabled="day.past"
                    data-test="schedule-day"
                    @click="pick(day.value)"
                >
                    {{ day.value.d }}
                </button>
            </div>
        </div>
        <div class="sched-time">
            <label for="sched-time">Time</label>
            <div class="input-icon">
                <span class="input-icon-addon"><i class="ti ti-clock"></i></span>
                <input
                    id="sched-time"
                    v-model="time"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-2][0-9]:[0-5][0-9]"
                    class="form-control"
                    aria-describedby="sched-sum"
                    data-test="schedule-time"
                />
            </div>
            <div class="sched-chips">
                <button
                    v-for="chip in chips"
                    :key="chip.label"
                    type="button"
                    class="btn btn-sm"
                    data-test="schedule-chip"
                    @click="pickChip(chip.at)"
                >
                    {{ chip.label }}
                </button>
            </div>
        </div>
        <p id="sched-sum" class="sched-sum" data-test="schedule-summary">
            <i class="ti ti-calendar-time"></i>Plays at {{ time }} {{ summaryDay }}.
        </p>
        <div class="sched-acts">
            <a
                :href="submitHref"
                class="btn btn-primary"
                :class="{disabled: !validTime}"
                :aria-disabled="!validTime"
                data-test="schedule-confirm"
                >Schedule for {{ time }}</a
            >
            <button type="button" class="btn" data-test="schedule-cancel" @click="emit('close')">Cancel</button>
        </div>
    </div>
</template>

<style scoped>
.sheet-dim {
    position: fixed;
    inset: 0;
    background: rgb(0 0 0 / 48%);
    z-index: 1050;
}
.sched-sheet {
    position: fixed;
    inset-inline: 0;
    inset-block: auto 0;
    z-index: 1060;
    max-block-size: 100dvh;
    overflow-y: auto;
    padding: 12px 16px 16px;
    background: var(--tblr-bg-surface);
    color: var(--tblr-body-color);
    border-radius: 16px 16px 0 0;
    box-shadow: 0 -8px 32px rgb(0 0 0 / 28%);
}
.sched-grab {
    display: block;
    inline-size: 100%;
    block-size: 20px;
    margin: -8px 0 0;
    padding: 0;
    border: 0;
    background: none;
}
.sched-grab span {
    display: block;
    inline-size: 40px;
    block-size: 4px;
    margin: 0 auto;
    border-radius: 4px;
    background: var(--tblr-border-color);
}
.sched-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.sched-head h2 {
    margin: 0;
    font-size: 1.0625rem;
    font-weight: 650;
}
.sched-head .btn-close {
    inline-size: 44px;
    block-size: 44px;
    margin: -10px -12px -10px 0;
    background-size: 12px;
    opacity: 0.7;
}
.sched-hint {
    margin: 6px 0 4px;
    font-size: 0.8125rem;
    color: var(--tblr-secondary-color);
}
.cal-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 4px;
}
.cal-nav .btn {
    inline-size: 44px;
    block-size: 44px;
}
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    text-align: center;
}
.cal-wk span {
    font-size: 0.75rem;
    color: var(--tblr-secondary-color);
    padding-block: 4px;
}
.cal-day {
    block-size: 44px;
    border: 0;
    border-radius: 50%;
    background: none;
    color: var(--tblr-body-color);
    font-size: 0.875rem;
    font-variant-numeric: tabular-nums;
    font-family: inherit;
}
.cal-day:hover:not(:disabled) {
    background: var(--tblr-bg-surface-secondary);
}
.cal-day:disabled {
    color: var(--tblr-secondary-color);
    opacity: 0.4;
}
.cal-day.is-out {
    color: var(--tblr-secondary-color);
}
.cal-day.is-sel {
    background: var(--tblr-primary);
    color: #fff;
    font-weight: 650;
    opacity: 1;
}
.cal-day:focus-visible {
    outline: 2px solid var(--tblr-primary);
    outline-offset: 2px;
}
.sched-time {
    margin-top: 6px;
}
.sched-time label {
    display: block;
    font-size: 0.8125rem;
    font-weight: 600;
    margin-bottom: 4px;
}
.sched-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}
.sched-chips .btn {
    min-block-size: 44px;
}
.sched-sum {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    margin: 14px 0 12px;
    font-size: 0.875rem;
    font-weight: 500;
}
.sched-sum .ti {
    font-size: 1.2rem;
    color: var(--tblr-secondary-color);
    margin-top: 1px;
}
.sched-acts {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.sched-acts .btn {
    min-block-size: 44px;
}
.sched-acts .btn-primary {
    flex: 1 1 auto;
}
</style>
