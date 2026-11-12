/**
 * Gráfico de los reportes (RF25, R-11 de la QA): barras horizontales de una sola serie con Chart.js.
 *
 * Una sola medida por gráfico, en el rojo UES y sin leyenda (el título dice qué es); el valor exacto sale al
 * pasar el puntero. La tabla de debajo sigue siendo la vista accesible y completa. Chart.js se carga con
 * import() dinámico, para no pesar en las demás pantallas.
 *
 * <div wire:key="grafico-…" x-data="graficoReporte(@js($grafico))"><canvas x-ref="lienzo"></canvas></div>
 */
export default (datos) => ({
    grafico: null,

    async init() {
        if (!datos.valores.length) {
            return;
        }
        const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } = await import('chart.js');
        Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

        const token = (nombre, porDefecto) => getComputedStyle(document.documentElement).getPropertyValue(nombre).trim() || porDefecto;
        const marca = token('--color-marca-600', '#ae152d');
        const borde = token('--color-borde', '#dcdcde');
        const suave = token('--color-texto-suave', '#696969');

        this.grafico = new Chart(this.$refs.lienzo, {
            type: 'bar',
            data: {
                labels: datos.etiquetas,
                datasets: [{ data: datos.valores, backgroundColor: marca, borderRadius: 4, borderSkipped: 'start', maxBarThickness: 18 }],
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                animation: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        max: datos.maximo ?? undefined,
                        grid: { color: borde },
                        border: { display: false },
                        ticks: { color: suave, precision: datos.enteros ? 0 : undefined },
                    },
                    y: { grid: { display: false }, ticks: { color: suave, autoSkip: false } },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => `${c.formattedValue}${datos.sufijo}` } },
                },
            },
        });
    },

    destroy() {
        this.grafico?.destroy();
    },
});
