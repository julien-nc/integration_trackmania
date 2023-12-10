<template>
	<LineChartJs
		:chart-data="chartData"
		:chart-options="baseLineChartOptions" />
</template>

<script>
import LineChartJs from './LineChartJs.vue'

export default {
	name: 'NbRecordsPerPosition',

	components: {
		LineChartJs,
	},

	props: {
		pbs: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			baseLineChartOptions: {
				elements: {
					line: {
						// by default, fill lines to the previous dataset
						// fill: '-1',
						// fill: 'origin',
						cubicInterpolationMode: 'monotone',
					},
				},
				scales: {
					y: {
						// stacked: true,
					},
					x: {
						// type: 'linear',
					},
				},
				plugins: {
					legend: {
						position: 'left',
					},
					tooltip: {
						intersect: false,
						mode: 'index',
					},
				},
				responsive: true,
				maintainAspectRatio: false,
				showAllTooltips: false,
				hover: {
					intersect: false,
					mode: 'index',
				},
			},
		}
	},

	computed: {
		chartData() {
			const countPerPosition = {}
			this.pbs.forEach(pb => {
				const position = pb.recordPosition.zones.World
				countPerPosition[position] = (countPerPosition[position] ?? 0) + 1
			})

			const positions = Object.keys(countPerPosition).sort((a, b) => a - b)
			// const maxPosition = positions.reduce((acc, position) => Math.max(acc, position))

			return {
				labels: positions,
				datasets: [
					{
						id: 'World',
						label: 'World',
						// FIXME hacky way to change alpha channel:
						// backgroundColor: '#' + member.color + '4D',
						// pointBackgroundColor: '#' + member.color,
						// borderColor: '#' + member.color,
						// pointHighlightStroke: '#' + member.color,
						// // lineTension: 0.2,
						data: positions.map(position => countPerPosition[position]),
						// hidden: parseInt(mid) === 0,
						// pointRadius: Array(this.realMonths.length).fill(0),
						fill: true,
						order: 0,
						borderWidth: 3,
					},
				],
			}
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
