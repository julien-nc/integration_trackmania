<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Trackmania\Util;

/**
 * Static helpers to pick a preview image out of a remote skin ZIP archive.
 * Nadeo only stores preview images of some skins, for the others the skin ZIP
 * is the only source available. Its ZIP structure allows fetching just the
 * preview with two range requests: one for the end of central directory record
 * and the central directory that followed it, one for the image entry itself.
 * Decompression is limited, huge entries like model textures are never touched.
 */
class SkinZipPreview {

	/** Decompressing entries bigger than these is useless, previews are a few MB at most */
	public const MAX_PREVIEW_SIZE = 8 * 1024 * 1024;
	/** Well known preview file names of skin ZIPs, most used one first */
	private const PREVIEW_RANK = [
		'icon.tga' => 0,
		'icon.png' => 1,
		'stadiumcaricon.tga' => 2,
		'icon.jpg' => 3,
		'icon.jpeg' => 4,
	];
	private const IMAGE_NAME_PATTERN = '/\.(tga|png|jpe?g)$/i';
	/** Nadeo car texture names, they resemble previews but cannot be displayed */
	private const TEXTURE_NAME_PATTERN = '/^(skin|wheels|details|glass)[_-]/i';

	/**
	 * @param string $tail last bytes of the ZIP file, must contain the end of central directory record
	 * @return array|null ['cdOffset' => int, 'cdSize' => int] or null when the record is not found
	 */
	public static function parseEOCD(string $tail): ?array {
		$eocd = strrpos($tail, "PK\x05\x06");
		if ($eocd === false) {
			return null;
		}
		$cdSize = self::unpackUint32(substr($tail, $eocd + 12));
		$cdOfs = self::unpackUint32(substr($tail, $eocd + 16));
		if ($cdSize === null || $cdOfs === null || $cdSize === 0xFFFFFFFF || $cdOfs === 0xFFFFFFFF) {
			// missing data or a zip64 archive we cannot range-read with 32-bit fields
			return null;
		}
		return ['cdOffset' => $cdOfs, 'cdSize' => $cdSize];
	}

	/**
	 * File entries of a central directory, any entry kind.
	 *
	 * @param string $centralDirectory the raw central directory data
	 * @return array list of ['name' => string, 'localHeaderOffset' => int, 'method' => int,
	 *               'compressedSize' => int, 'uncompressedSize' => int]
	 */
	public static function listEntries(string $centralDirectory): array {
		$entries = [];
		$pos = 0;
		$length = strlen($centralDirectory);
		while ($pos + 46 <= $length && substr($centralDirectory, $pos, 4) === "PK\x01\x02") {
			$nameLength = unpack('v', substr($centralDirectory, $pos + 28, 2))[1];
			$extraLength = unpack('v', substr($centralDirectory, $pos + 30, 2))[1];
			$commentLength = unpack('v', substr($centralDirectory, $pos + 32, 2))[1];
			$entries[] = [
				'name' => substr($centralDirectory, $pos + 46, $nameLength),
				'localHeaderOffset' => self::unpackUint32(substr($centralDirectory, $pos + 42)) ?? 0,
				'method' => unpack('v', substr($centralDirectory, $pos + 10, 2))[1],
				'compressedSize' => self::unpackUint32(substr($centralDirectory, $pos + 20)) ?? 0,
				'uncompressedSize' => self::unpackUint32(substr($centralDirectory, $pos + 24)) ?? 0,
			];
			$pos += 46 + $nameLength + $extraLength + $commentLength;
		}
		return $entries;
	}

	/**
	 * Reduce an entry list to the displayable preview images, promising ones first:
	 * well known preview file names in rank order, then any other image entry,
	 * excluding car texture names which are too big or undisplayable (e.g. DDS-converted TGAs).
	 *
	 * @param array $entries as returned by listEntries()
	 * @return array filtered and sorted entry list
	 */
	public static function filterPreviewEntries(array $entries): array {
		$ranked = [];
		$others = [];
		foreach (array_values($entries) as $index => $entry) {
			if ($entry['method'] !== 0 && $entry['method'] !== 8) {
				continue;
			}
			if ($entry['compressedSize'] < 1
				|| $entry['uncompressedSize'] < 1
				|| $entry['uncompressedSize'] > self::MAX_PREVIEW_SIZE) {
				continue;
			}
			$baseName = strtolower(pathinfo($entry['name'], PATHINFO_BASENAME));
			if (!preg_match(self::IMAGE_NAME_PATTERN, $baseName)
				|| preg_match(self::TEXTURE_NAME_PATTERN, $baseName)) {
				continue;
			}
			$rank = self::PREVIEW_RANK[$baseName] ?? null;
			if ($rank !== null && !isset($ranked[$rank])) {
				$ranked[$rank] = $entry;
			} else {
				$others[] = $entry;
			}
		}
		ksort($ranked);
		return array_merge(array_values($ranked), $others);
	}

	/**
	 * Entry data starts after the local file header, its name (and its extra field)
	 *
	 * @param string $localHeader first 30+ bytes of a local file header, i.e. data of one range request slice
	 * @return int|null absolute offset of the entry data, relative to the beginning of the file
	 */
	public static function localDataOffset(string $localHeader): ?int {
		if (strlen($localHeader) < 30 || substr($localHeader, 0, 4) !== "PK\x03\x04") {
			return null;
		}
		$nameLength = unpack('v', substr($localHeader, 26, 2))[1];
		$extraLength = unpack('v', substr($localHeader, 28, 2))[1];
		return 30 + $nameLength + $extraLength;
	}

	/**
	 * Decompress one entry, stored and deflate methods.
	 *
	 * @param string $data the compressed entry data
	 * @param int $method zip compression method
	 * @param int $uncompressedSize announced size of the decompressed data
	 * @return string|null the raw image bytes, null when the entry cannot be decompressed as announced
	 */
	public static function decodeEntry(string $data, int $method, int $uncompressedSize): ?string {
		if ($method === 0) {
			return strlen($data) >= $uncompressedSize ? substr($data, 0, $uncompressedSize) : null;
		}
		if ($method !== 8) {
			return null;
		}
		$inflated = @gzinflate($data, $uncompressedSize);
		return $inflated === false ? null : $inflated;
	}

	/**
	 * @return int|null big-endian unsigned 32 bit value, null when 4 bytes are not available
	 */
	private static function unpackUint32(string $bytes): ?int {
		if (strlen($bytes) < 4) {
			return null;
		}
		return unpack('V', substr($bytes, 0, 4))[1];
	}
}
