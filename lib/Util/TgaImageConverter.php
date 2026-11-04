<?php

/**
 * Nextcloud - Trackmania
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2026
 */

namespace OCA\Trackmania\Util;

/**
 * Convert TARGA image data into PNG strings.
 * Nadeo provides skin thumbnails as TGA files which browsers cannot render,
 * and GD neither, Imagick is usually not available.
 */
class TgaImageConverter {

	/**
	 * Convert uncompressed (type 2) and RLE (type 10) truecolor TGA data,
	 * anything else (color map indexed images, grayscale, exotic depths) is not supported.
	 *
	 * @return string|null the PNG data or null when the input cannot be converted
	 */
	public static function convertToPng(string $tgaData): ?string {
		if (strlen($tgaData) < 18) {
			return null;
		}
		/*
		 * TGA header: ID length, color map type, image type, color map spec (3, 2, 2 and 1 bytes),
		 * then x/y origin, width, height, pixel depth and descriptor (2, 2, 2, 2, 1 and 1 bytes)
		 */
		$header = unpack(
			'CidLength/CcolorMapType/CimageType/vcolorMapFirstIndex/vcolorMapLength/CcolorMapDepth/vxOrigin/vyOrigin/vwidth/vheight/CpixelDepth/Cdescriptor',
			substr($tgaData, 0, 18),
		);
		if (!is_array($header)) {
			return null;
		}
		if (!in_array($header['imageType'], [2, 10], true)
			|| !in_array($header['pixelDepth'], [24, 32], true)) {
			return null;
		}

		$bodyOffset = 18 + $header['idLength'];
		if ($header['colorMapType'] !== 0) {
			// color map data comes before the image data even for truecolor images
			$bodyOffset += $header['colorMapLength'] * intdiv($header['colorMapDepth'], 8);
		}
		$body = substr($tgaData, $bodyOffset);
		$bytesPerPixel = intdiv($header['pixelDepth'], 8);

		$pixelCount = $header['width'] * $header['height'];
		if ($header['imageType'] === 2) {
			$pixels = substr($body, 0, $pixelCount * $bytesPerPixel);
			if (strlen($pixels) < $pixelCount * $bytesPerPixel) {
				return null;
			}
		} else {
			$pixels = self::decodeRle($body, $bytesPerPixel, $pixelCount * $bytesPerPixel);
			if ($pixels === null) {
				return null;
			}
		}

		$topDown = ($header['descriptor'] & 0x20) !== 0;
		$flippedHorizontally = ($header['descriptor'] & 0x10) !== 0;
		$width = $header['width'];
		$rows = [];
		for ($row = 0; $row < $header['height']; $row++) {
			$rows[] = self::convertPixels(
				substr($pixels, $row * $width * $bytesPerPixel, $width * $bytesPerPixel),
				$bytesPerPixel,
				$flippedHorizontally,
			);
		}
		if (!$topDown) {
			// TGA rows are stored bottom-up by default
			$rows = array_reverse($rows);
		}

		$scanLines = '';
		foreach ($rows as $convertedRow) {
			// no filter
			$scanLines .= "\0" . $convertedRow;
		}
		$ihdr = pack('N2C5', $width, $header['height'], 8, 6, 0, 0, 0);
		return "\x89PNG\r\n\x1a\n"
			. self::pngChunk('IHDR', $ihdr)
			. self::pngChunk('IDAT', gzcompress($scanLines))
			. self::pngChunk('IEND', '');
	}

	/**
	 * Decode RLE truecolor data packets until the expected number of pixels is reached
	 */
	private static function decodeRle(string $body, int $bytesPerPixel, int $expectedLength): ?string {
		$pixels = '';
		$offset = 0;
		$bodyLength = strlen($body);
		while (strlen($pixels) < $expectedLength && $offset < $bodyLength) {
			$packetHeader = ord($body[$offset]);
			$offset++;
			$packetLength = ($packetHeader & 0x7f) + 1;
			if (($packetHeader & 0x80) !== 0) {
				$pixel = substr($body, $offset, $bytesPerPixel);
				if (strlen($pixel) < $bytesPerPixel) {
					return null;
				}
				$pixels .= str_repeat($pixel, $packetLength);
				$offset += $bytesPerPixel;
			} else {
				$pixels .= substr($body, $offset, $packetLength * $bytesPerPixel);
				$offset += $packetLength * $bytesPerPixel;
			}
		}
		if (strlen($pixels) !== $expectedLength) {
			return null;
		}
		return $pixels;
	}

	/**
	 * TGA pixels are BGRA/BGR, PNG lines want RGBA
	 */
	private static function convertPixels(string $pixels, int $bytesPerPixel, bool $flippedHorizontally): string {
		$rgba = '';
		$start = $flippedHorizontally ? strlen($pixels) - $bytesPerPixel : 0;
		$step = $flippedHorizontally ? -$bytesPerPixel : $bytesPerPixel;
		$end = $flippedHorizontally ? -1 : strlen($pixels);
		for ($i = $start; $i !== $end; $i += $step) {
			$rgba .= $pixels[$i + 2]
				. $pixels[$i + 1]
				. $pixels[$i]
				. ($bytesPerPixel === 4 ? $pixels[$i + 3] : "\xff");
		}
		return $rgba;
	}

	private static function pngChunk(string $type, string $data): string {
		return pack('N', strlen($data)) . $type . $data . hash('crc32b', $type . $data, true);
	}
}
