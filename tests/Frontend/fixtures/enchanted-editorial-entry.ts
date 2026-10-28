import { createSSRApp } from 'vue';
import EnchantedEditorialFixture from './EnchantedEditorialFixture.vue';
const params = new URLSearchParams(location.search);
createSSRApp(EnchantedEditorialFixture, { variant: params.get('case'), canvas: params.get('canvas') === '1' }).mount('#app');
