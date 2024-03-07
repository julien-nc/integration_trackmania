import { TextFormatter, Time } from 'tm-essentials'
import { htmlify } from 'tm-text'
import moment from '@nextcloud/moment'
import { imagePath, generateUrl } from '@nextcloud/router'

const MEDAL_STRING = {
	0: t('integration_trackmania', 'None'),
	1: t('integration_trackmania', 'Bronze'),
	2: t('integration_trackmania', 'Silver'),
	3: t('integration_trackmania', 'Gold'),
	4: t('integration_trackmania', 'Author'),
}

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
export function getFlagCode(zone) {
	if (zone === undefined) {
		return 'WOR'
	}
	let z = zone
	const flags = [z.flag]
	while (z.parent) {
		z = z.parent
		flags.push(z.flag)
	}
	if (flags.length > 2) {
		return flags[flags.length - 3]
	} else if (flags.length > 1) {
		return flags[flags.length - 2]
	}
	return 'WOR'
}
export function getFlagUrl(zone) {
	return generateUrl('/apps/integration_trackmania/flag/{code}', { code: getFlagCode(zone) })
}
export function getZoneDisplayName(zone) {
	if (zone === undefined) {
		return t('integration_trackmania', 'No location found')
	}
	let z = zone
	const names = [z.name]
	while (z.parent) {
		z = z.parent
		names.push(z.name)
	}
	return names.join(', ')
}
export function formatPbs(pbs) {
	for (let i = 0; i < pbs.length; i++) {
		const pb = pbs[i]
		const name = pb.mapInfo.name
		pb.mapInfo.cleanName = TextFormatter.deformat(name)
		pb.mapInfo.htmlName = htmlify(name)

		pb.record.recordScore.formattedTime = formatTime(pb.record.recordScore.time)
		if (pb.otherRecord?.time) {
			const delta = pb.record.recordScore.time - pb.otherRecord.time
			const prefix = delta > 0 ? '+' : ''
			pb.otherRecord.formattedTime = formatTime(pb.otherRecord.time)
			pb.otherRecord.delta = delta
			pb.otherRecord.formattedDelta = prefix + formatTime(delta)
			pb.otherRecord.medal = pb.otherRecord.record.medal
			pb.otherRecord.formattedMedal = formatMedals(pb.otherRecord.record.medal)
			pb.otherRecord.formattedDate = formatTimestamp(pb.otherRecord.unix_timestamp)
			pb.otherRecord.formattedDateWithZone = formatTimestampWithZone(pb.otherRecord.unix_timestamp)
		}
		pb.record.formattedDate = formatTimestamp(pb.record.unix_timestamp)
		pb.record.formattedDateWithZone = formatTimestampWithZone(pb.record.unix_timestamp)
		pb.record.formattedMedal = formatMedals(pb.record.medal)

		pb.mapInfo.formattedAuthorTime = formatMedalTime(pb, 4)
		pb.mapInfo.formattedGoldTime = formatMedalTime(pb, 3)
		pb.mapInfo.formattedSilverTime = formatMedalTime(pb, 2)
		pb.mapInfo.formattedBronzeTime = formatMedalTime(pb, 1)
	}
	return pbs
}
function formatMedals(value) {
	return MEDAL_STRING[value]
}
function formatTimestamp(value) {
	return moment.unix(value).format('LLL')
}
function formatTimestampWithZone(value) {
	return moment.unix(value).format('LLL (Z)')
}
function formatTime(value) {
	return Time.fromMilliseconds(value).toTmString()
}
function formatMedalTime(pb, medal) {
	if (medal === MEDAL.AUTHOR) {
		const delta = pb.record.recordScore.time - pb.mapInfo.authorTime
		const prefix = delta > 0 ? '+' : ''
		return t('integration_trackmania', 'Author time: {t} ({delta})', { t: formatTime(pb.mapInfo.authorTime), delta: prefix + formatTime(delta) })
	} else if (medal === MEDAL.GOLD) {
		const delta = pb.record.recordScore.time - pb.mapInfo.goldTime
		const prefix = delta > 0 ? '+' : ''
		return t('integration_trackmania', 'Gold time: {t} ({delta})', { t: formatTime(pb.mapInfo.goldTime), delta: prefix + formatTime(delta) })
	} else if (medal === MEDAL.SILVER) {
		const delta = pb.record.recordScore.time - pb.mapInfo.silverTime
		const prefix = delta > 0 ? '+' : ''
		return t('integration_trackmania', 'Silver time: {t} ({delta})', { t: formatTime(pb.mapInfo.silverTime), delta: prefix + formatTime(delta) })
	} else if (medal === MEDAL.BRONZE) {
		const delta = pb.record.recordScore.time - pb.mapInfo.bronzeTime
		const prefix = delta > 0 ? '+' : ''
		return t('integration_trackmania', 'Bronze time: {t} ({delta})', { t: formatTime(pb.mapInfo.bronzeTime), delta: prefix + formatTime(delta) })
	}
	return ''
}

export const authorMedalImageUrl = imagePath('integration_trackmania', 'medal.author.png')
export const goldMedalImageUrl = imagePath('integration_trackmania', 'medal.gold.png')
export const silverMedalImageUrl = imagePath('integration_trackmania', 'medal.silver.png')
export const bronzeMedalImageUrl = imagePath('integration_trackmania', 'medal.bronze.custom.png')

export const MEDAL = {
	AUTHOR: 4,
	GOLD: 3,
	SILVER: 2,
	BRONZE: 1,
	NOTHING: 0,
}

export function getMedalImageUrl(medal) {
	if (medal === MEDAL.AUTHOR) {
		return authorMedalImageUrl
	} else if (medal === MEDAL.GOLD) {
		return goldMedalImageUrl
	} else if (medal === MEDAL.SILVER) {
		return silverMedalImageUrl
	} else if (medal === MEDAL.BRONZE) {
		return bronzeMedalImageUrl
	}
	return ''
}

export function getFormattedBestMedal(pb) {
	if (pb.record.medal === 4) {
		return pb.mapInfo.formattedAuthorTime
	} else if (pb.record.medal === 3) {
		return pb.mapInfo.formattedGoldTime
	} else if (pb.record.medal === 2) {
		return pb.mapInfo.formattedSilverTime
	} else if (pb.record.medal === 1) {
		return pb.mapInfo.formattedBronzeTime
	}
	return ''
}
