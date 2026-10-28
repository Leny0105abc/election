import Chart from 'chart.js/auto';

window.Chart = Chart;

// Alpine is bundled with Livewire; register our components when it boots.
document.addEventListener('alpine:init', () => {
    /**
     * <div x-data="chart(@js($config))"><canvas x-ref="canvas"></canvas></div>
     * Re-render by changing the element's wire:key when the data changes.
     */
    window.Alpine.data('chart', (config) => ({
        chart: null,
        init() {
            this.chart = new Chart(this.$refs.canvas, config);
        },
        destroy() {
            this.chart?.destroy();
        },
    }));

    /** Toast notifications: $this->dispatch('notify', message: '...', type: 'success') */
    window.Alpine.data('toasts', () => ({
        items: [],
        add(detail) {
            const payload = Array.isArray(detail) ? detail[0] : detail;
            const id = Date.now() + Math.random();
            this.items.push({ id, message: payload.message, type: payload.type || 'success' });
            setTimeout(() => this.remove(id), 5000);
        },
        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    }));
});
