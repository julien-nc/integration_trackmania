<template>
	<table class="simple-table">
		<thead>
			<tr>
				<th v-for="c in columns"
					:key="c.field"
					class="header"
					@click="onClick($event, c)">
					<div class="label">
						<span class="title"
							:class="{ sorted: !!columnSortSuffix[c.sortName] }">
							{{ c.label }}
						</span>
						<span class="spacer" />
						<span v-if="columnSortSuffix[c.sortName]" class="sort-suffix">
							{{ columnSortSuffix[c.sortName] }}
						</span>
						<span v-else-if="c.sortName" class="sort-suffix">
							<span>⯅</span>
							<span>⯆</span>
						</span>
					</div>
				</th>
			</tr>
			<tr>
				<th v-for="c in columns"
					:key="c.field"
					class="filter-header">
					<slot name="filter" :column="c" />
				</th>
			</tr>
		</thead>
		<tr v-for="(r, i) in rows"
			:key="r.mapId">
			<td v-for="c in columns"
				:key="c.field + '-' + r.mapId"
				:class="{ [c.type]: true, [c.tdClass]: true }"
				@click="$emit('cell-clicked', c, r)">
				<slot name="cell"
					:column="c"
					:row="r"
					:index="i" />
			</td>
		</tr>
	</table>
</template>

<script>

export default {
	name: 'SimpleTable',

	components: {
	},

	props: {
		columns: {
			type: Array,
			required: true,
		},
		rows: {
			type: Array,
			required: true,
		},
		sortOptions: {
			type: Array,
			required: true,
		},
	},

	emits: [
		'cell-clicked',
		'header-clicked',
		'header-shift-clicked',
	],

	data() {
		return {
		}
	},

	computed: {
		columnSortSuffix() {
			const suffixByFieldName = {}
			this.sortOptions.forEach((so, i) => {
				suffixByFieldName[so.sortName] = (i + 1) + (so.order === 'asc' ? '⯅' : '⯆')
			})
			return suffixByFieldName
		},
	},

	watch: {
	},

	beforeMount() {
	},

	mounted() {
	},

	methods: {
		onClick(e, c) {
			if (!c.sortName) {
				return
			}
			if (e.shiftKey) {
				this.$emit('header-shift-clicked', c)
			} else {
				this.$emit('header-clicked', c)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.simple-table {
	position: relative;
	// take what the columns need, at most the available width:
	// never stretched beyond the content, never squeezed below the min content
	width: fit-content;
	counter-reset: simple-table-row;
	// the line-number cell content is browser-computed in the rendered row
	// order, so re-filtering/re-sorting the rows never patches it: the counter
	// is incremented and read by the same pseudo-element (also robust if the
	// rows are direct table children instead of tbody rows)
	td.lineNumberColumn::before {
		counter-increment: simple-table-row;
		content: counter(simple-table-row);
	}

	border-collapse: separate;
	border-spacing: 0;

	&,
	th,
	td {
		border: none;
	}

	tr {
		td {
			border-bottom: 1px solid var(--tm-line);
			border-inline-start: 1px solid var(--tm-line);
			border-inline-end: 1px solid var(--tm-line);
			padding: 6px;
			text-align: right;
		}

		&:first-of-type td {
			border-top: 1px solid var(--tm-line);
		}

		&:hover {
			td {
				background-color: color-mix(in srgb, var(--color-primary-element-hover) 40%, transparent);
			}
		}

		.header {
			user-select: none;
			cursor: pointer;
			* {
				cursor: pointer;
			}
			.label {
				display: flex;

				.spacer {
					flex-grow: 1;
				}
				.title {
					&.sorted {
						font-weight: bold;
						color: var(--tm-cyan);
						text-shadow: 0 0 12px rgba(31, 212, 232, 0.35);
					}
				}
			}
		}

		th {
			background-color: var(--tm-surface-strong);
			backdrop-filter: blur(10px);
			-webkit-backdrop-filter: blur(10px);
			border-bottom: 1px solid var(--tm-ring);
			border-inline-start: 1px solid var(--tm-line);
			border-inline-end: 1px solid var(--tm-line);
			padding: 6px;
			text-align: right;
			color: var(--color-main-text);
			white-space: nowrap;

			&.header {
				position: sticky;
				top: -30px;
				z-index: 999;
			}
			&.filter-header {
				position: sticky;
				top: 4px;
				z-index: 999;
				border-bottom: 1px solid var(--tm-line);
			}
			.sort-suffix {
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				line-height: 12px;
				color: var(--tm-cyan);
			}
		}
	}
}
</style>
