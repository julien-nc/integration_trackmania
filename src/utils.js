let mytimer = 0
export function delay(callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(mytimer)
		mytimer = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

// utility function to get nested property
export function dig(obj, selector) {
	let result = obj
	const splitter = selector.split('.')
	for (let i = 0; i < splitter.length; i++) {
		if (typeof result === 'undefined' || result === null) {
			return undefined
		}
		result = result[splitter[i]]
	}
	return result
}
