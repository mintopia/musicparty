<script setup>
import qrcode from 'qrcode-generator';
import {computed} from 'vue';

const props = defineProps({value: {type: String, required: true}, size: {type: Number, default: 100}});

const svg = computed(() => {
    const qr = qrcode(0, 'M');
    qr.addData(props.value);
    qr.make();
    return qr.createSvgTag({cellSize: 4, margin: 2, scalable: true});
});
</script>

<template>
    <div
        data-testid="qr-code"
        role="img"
        :aria-label="`QR code to join: ${value}`"
        class="bg-white [&>svg]:h-full [&>svg]:w-full"
        :style="{width: `${size}px`, height: `${size}px`}"
        v-html="svg"
    ></div>
</template>
