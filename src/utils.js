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

export const MEDAL = {
	AUTHOR: 4,
	GOLD: 3,
	SILVER: 2,
	BRONZE: 1,
	NOTHING: 0,
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
export function getFlagUrlFromZone(zone) {
	return generateUrl('/apps/integration_trackmania/flag/{code}', { code: getFlagCode(zone) })
}
export function getFlagUrlFromCode(code) {
	return generateUrl('/apps/integration_trackmania/flag/{code}', { code })
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
	// console.debug('[trackmania] PBS to format', pbs)
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
		formatWorldRecord(pb)
		pb.record.formattedDate = formatTimestamp(pb.record.unix_timestamp)
		pb.record.formattedDateWithZone = formatTimestampWithZone(pb.record.unix_timestamp)
		pb.record.formattedMedal = formatMedals(pb.record.medal)

		pb.mapInfo.medalTimes = {
			author: formatMedalTime(pb, MEDAL.AUTHOR),
			gold: formatMedalTime(pb, MEDAL.GOLD),
			silver: formatMedalTime(pb, MEDAL.SILVER),
			bronze: formatMedalTime(pb, MEDAL.BRONZE),
		}

		if (pb.bestKnownPosition.position !== pb.recordPosition.zones.World) {
			pb.bestKnownPosition.formattedLastSeenAt = formatTimestamp(pb.bestKnownPosition.last_seen_at)
			pb.bestKnownPosition.lastSeenAt = pb.bestKnownPosition.last_seen_at
		}
	}
	return pbs
}
export function formatWorldRecord(pb) {
	if (!pb.worldRecord) {
		return
	}
	pb.worldRecord.formattedTime = formatTime(pb.worldRecord.time)
	const wrDelta = pb.record.recordScore.time - pb.worldRecord.time
	pb.worldRecord.delta = wrDelta
	pb.worldRecord.formattedDelta = (wrDelta > 0 ? '+' : '') + formatTime(wrDelta)
	if (pb.worldRecord.unix_timestamp) {
		pb.worldRecord.formattedDate = formatTimestamp(pb.worldRecord.unix_timestamp)
		pb.worldRecord.formattedDateWithZone = formatTimestampWithZone(pb.worldRecord.unix_timestamp)
	}
}
function formatMedals(value) {
	return MEDAL_STRING[value]
}
function formatTimestamp(value) {
	return moment.unix(value).format('YYYY-MM-DD HH:mm')
}
function formatTimestampWithZone(value) {
	return moment.unix(value).format('LLL (Z)')
}
function formatTime(value) {
	return Time.fromMilliseconds(value).toTmString()
}
function formatMedalTime(pb, medal) {
	const medalInfo = {
		[MEDAL.AUTHOR]: { label: t('integration_trackmania', 'Author time'), value: pb.mapInfo.authorTime },
		[MEDAL.GOLD]: { label: t('integration_trackmania', 'Gold time'), value: pb.mapInfo.goldTime },
		[MEDAL.SILVER]: { label: t('integration_trackmania', 'Silver time'), value: pb.mapInfo.silverTime },
		[MEDAL.BRONZE]: { label: t('integration_trackmania', 'Bronze time'), value: pb.mapInfo.bronzeTime },
	}[medal]
	const delta = pb.record.recordScore.time - medalInfo.value
	const prefix = delta > 0 ? '+' : ''
	return {
		label: medalInfo.label,
		formattedTime: formatTime(medalInfo.value),
		delta,
		formattedDelta: prefix + formatTime(delta),
	}
}

export const authorMedalImageUrl = imagePath('integration_trackmania', 'medal.author.png')
export const goldMedalImageUrl = imagePath('integration_trackmania', 'medal.gold.png')
export const silverMedalImageUrl = imagePath('integration_trackmania', 'medal.silver.png')
export const bronzeMedalImageUrl = imagePath('integration_trackmania', 'medal.bronze.custom.png')

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
	const medalTimes = pb.mapInfo.medalTimes ?? {}
	const bestMedal = {
		[MEDAL.AUTHOR]: medalTimes.author,
		[MEDAL.GOLD]: medalTimes.gold,
		[MEDAL.SILVER]: medalTimes.silver,
		[MEDAL.BRONZE]: medalTimes.bronze,
	}[pb.record.medal]
	if (!bestMedal) {
		return ''
	}
	return t('integration_trackmania', '{label}: {t} ({delta})', {
		label: bestMedal.label, t: bestMedal.formattedTime, delta: bestMedal.formattedDelta,
	})
}
