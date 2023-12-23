<template>
	<table class="simple-table">
		<thead>
			<tr>
				<th v-for="c in columns"
					:key="c.field"
					class="header"
					@click="onClick($event, c)">
					<div class="label">
						<span>
							{{ c.label }}
						</span>
						<span class="spacer" />
						<span class="sort-suffix">
							{{ columnSortSuffix[c.sortName] }}
						</span>
					</div>
				</th>
			</tr>
			<tr>
				<th v-for="c in columns"
					:key="c.field">
					<slot name="filter" :column="c" />
				</th>
			</tr>
		</thead>
		<tr v-for="(r, i) in rows"
			:key="r.id">
			<td v-for="c in columns"
				:key="c.field + '-' + r.id"
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

	emit: [
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
				suffixByFieldName[so.sortName] = (i + 1) + (so.order === 'asc' ? '⮟' : '⮝')
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
	&, th, td {
		border: 1px solid var(--color-border-dark);
		border-collapse: collapse;
	}
	tr {
		.header {
			cursor: pointer;
			* {
				cursor: pointer;
			}
			.label {
				display: flex;

				.spacer {
					flex-grow: 1;
				}
			}
		}
		&:hover {
			background-color: var(--color-background-hover);
		}
		th {
			background-color: var(--color-primary-element-light);
		}

		th, td {
			padding: 4px;
		}

		td {
			text-align: right;
		}
	}
}
</style>
