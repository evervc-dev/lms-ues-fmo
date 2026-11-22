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
        const texto = token('--color-texto', '#1f1f1f');
        const neutra = token('--color-zinc-400', '#a1a1aa');
        // Con mínimo (asistencia 75 %, nota 6.0): en rojo solo lo que queda por debajo, el resto en gris.
        const umbral = datos.umbral ?? null;
        const colores = datos.valores.map((v) => (umbral === null || v < umbral ? marca : neutra));

        // El valor al final de cada barra y la línea del mínimo, sin cargar plugins extra.
        const rotulos = {
            id: 'rotulos',
            afterDatasetsDraw(chart) {
                const { ctx, chartArea, scales } = chart;
                ctx.save();
                ctx.font = '600 11px system-ui, sans-serif';
                ctx.fillStyle = texto;
                ctx.textBaseline = 'middle';
                chart.getDatasetMeta(0).data.forEach((barra, i) => {
                    const valor = datos.valores[i];
                    const escrito = (datos.enteros ? String(Math.round(valor)) : valor.toLocaleString('es-SV', { maximumFractionDigits: 1 })) + datos.sufijo;
                    const ancho = ctx.measureText(escrito).width;
                    const x = Math.min(barra.x + 6, chartArea.right - ancho - 2);
                    ctx.fillText(escrito, x, barra.y);
                });
                if (umbral !== null) {
                    const x = scales.x.getPixelForValue(umbral);
                    ctx.strokeStyle = texto;
                    ctx.setLineDash([4, 4]);
                    ctx.beginPath();
                    ctx.moveTo(x, chartArea.top);
                    ctx.lineTo(x, chartArea.bottom);
                    ctx.stroke();
                    ctx.setLineDash([]);
                    ctx.fillStyle = suave;
                    ctx.textBaseline = 'bottom';
                    ctx.fillText(`mínimo ${umbral.toLocaleString('es-SV')}${datos.sufijo}`, x + 4, chartArea.top - 2);
                }
                ctx.restore();
            },
        };

        this.grafico = new Chart(this.$refs.lienzo, {
            type: 'bar',
            data: {
                labels: datos.etiquetas,
                datasets: [{ data: datos.valores, backgroundColor: colores, borderRadius: 4, borderSkipped: 'start', maxBarThickness: 18 }],
            },
            plugins: [rotulos],
            options: {
                layout: { padding: { top: umbral === null ? 0 : 16, right: 44 } },
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
