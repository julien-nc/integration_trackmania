<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Trackmania\Tests\Unit;

use OCA\Trackmania\Util\SkinZipPreview;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/Util/SkinZipPreview.php';

class SkinZipPreviewTest extends TestCase {

	/**
	 * Build a ZIP archive by hand, entries are compressed but quality checked
	 *
	 * @param array $fileData name => [data, zip compression method (0 or 8)]
	 */
	private function makeZip(array $fileData, bool $withLocalExtra = false): string {
		$local = '';
		$central = '';
		$offset = 0;
		$entryCount = 0;
		foreach ($fileData as $name => [$data, $method]) {
			$compressed = $method === 8 ? gzdeflate($data) : $data;
			// central and local headers may differ on their extra field length,
			// mimic the real archives when wanted
			$localExtra = $withLocalExtra ? str_repeat('x', 17) : '';
			$centralExtra = '';
			$local
				.= "PK\x03\x04"
				. pack('v', 20)
				. pack('v', 0)
				. pack('v', $method)
				. pack('v', 0) . pack('v', 0)
				. pack('V', 0)
				. pack('V', strlen($compressed))
				. pack('V', strlen($data))
				. pack('v', strlen($name))
				. pack('v', strlen($localExtra))
				. $name
				. $localExtra
				. $compressed;
			$central
				.= "PK\x01\x02"
				. pack('v', 20)
				. pack('v', 20)
				. pack('v', 0)
				. pack('v', $method)
				. pack('v', 0) . pack('v', 0)
				. pack('V', 0)
				. pack('V', strlen($compressed))
				. pack('V', strlen($data))
				. pack('v', strlen($name))
				. pack('v', strlen($centralExtra))
				. pack('v', 0)
				. pack('v', 0)
				. pack('v', 0)
				. pack('V', 0)
				. pack('V', $offset)
				. $name
				. $centralExtra;
			$offset += 30 + strlen($name) + strlen($localExtra) + strlen($compressed);
			$entryCount++;
		}

		$centralDirectory = $central;
		$eocd = "PK\x05\x06"
			. pack('v', 0)
			. pack('v', 0)
			. pack('v', $entryCount)
			. pack('v', $entryCount)
			. pack('V', strlen($centralDirectory))
			. pack('V', $offset)
			. pack('v', 0);

		return $local . $centralDirectory . $eocd;
	}

	public function testParseEOCD(): void {
		$zip = $this->makeZip([
			'Icon.tga' => [str_repeat('t', 100), 0],
		]);
		$tail = substr($zip, max(0, strlen($zip) - 65536));
		$eocd = SkinZipPreview::parseEOCD($tail);
		$expectedCdOffset = 30 + strlen('Icon.tga') + 100;
		$this->assertIsArray($eocd);
		$this->assertSame($expectedCdOffset, $eocd['cdOffset']);
		$this->assertSame(strlen($zip) - $expectedCdOffset - 22, $eocd['cdSize']);
	}

	public function testParseEOCDGarbage(): void {
		$this->assertNull(SkinZipPreview::parseEOCD('not a zip at all'));
		$this->assertNull(SkinZipPreview::parseEOCD(''));
	}

	public function testParseEOCDZip64(): void {
		$tail = "PK\x05\x06"
			. pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('v', 0)
			. pack('V', 0xFFFFFFFF)
			. pack('V', 0xFFFFFFFF)
			. pack('v', 0);
		$this->assertNull(SkinZipPreview::parseEOCD($tail));
	}

	public function testListEntries(): void {
		$zip = $this->makeZip([
			'Icon.tga' => [str_repeat('a', 1000), 0],
			'Skin_B.tga' => [str_repeat('b', 10), 8],
		]);
		$eocd = SkinZipPreview::parseEOCD(substr($zip, max(0, strlen($zip) - 65536)));
		$this->assertIsArray($eocd);
		$entries = SkinZipPreview::listEntries(substr($zip, $eocd['cdOffset'], $eocd['cdSize']));
		$this->assertCount(2, $entries);
		$this->assertSame('Icon.tga', $entries[0]['name']);
		$this->assertSame(0, $entries[0]['localHeaderOffset']);
		$this->assertSame(0, $entries[0]['method']);
		$this->assertSame(1000, $entries[0]['uncompressedSize']);
		$this->assertSame('Skin_B.tga', $entries[1]['name']);
		$this->assertSame(8, $entries[1]['method']);
		$this->assertSame(10, $entries[1]['uncompressedSize']);
		// offsets depend on the first entry
		$this->assertSame(30 + strlen('Icon.tga') + 1000, $entries[1]['localHeaderOffset']);
	}

	public function testFilterPreviewEntriesSkipTexturesAndRankWellKnownNames(): void {
		$entries = [
			['name' => 'Skin_B.tga', 'localHeaderOffset' => 0, 'method' => 0, 'compressedSize' => 10, 'uncompressedSize' => 10],
			['name' => 'Details_fake.png', 'localHeaderOffset' => 1, 'method' => 0, 'compressedSize' => 10, 'uncompressedSize' => 10],
			['name' => 'models/car/icon.png', 'localHeaderOffset' => 2, 'method' => 8, 'compressedSize' => 10, 'uncompressedSize' => 40],
			['name' => 'ICON.TGA', 'localHeaderOffset' => 3, 'method' => 0, 'compressedSize' => 10, 'uncompressedSize' => 40],
			['name' => 'StadiumCarIcon.tga', 'localHeaderOffset' => 4, 'method' => 0, 'compressedSize' => 10, 'uncompressedSize' => 40],
			['name' => 'optimizer_version.txt', 'localHeaderOffset' => 5, 'method' => 0, 'compressedSize' => 10, 'uncompressedSize' => 40],
			['name' => 'random_image.png', 'localHeaderOffset' => 6, 'method' => 0, 'compressedSize' => 10, 'uncompressedSize' => 40],
			['name' => 'huge_preview.jpg', 'localHeaderOffset' => 7, 'method' => 0, 'compressedSize' => 10, 'uncompressedSize' => SkinZipPreview::MAX_PREVIEW_SIZE + 1],
			['name' => 'encrypted.png', 'localHeaderOffset' => 8, 'method' => 99, 'compressedSize' => 10, 'uncompressedSize' => 40],
			['name' => 'empty.png', 'localHeaderOffset' => 9, 'method' => 0, 'compressedSize' => 0, 'uncompressedSize' => 40],
		];
		$filtered = SkinZipPreview::filterPreviewEntries($entries);
		$names = array_map(static function (array $entry) {
			return $entry['name'];
		}, $filtered);
		// basename matching ranks nested well known names too
		$this->assertSame(['ICON.TGA', 'models/car/icon.png', 'StadiumCarIcon.tga', 'random_image.png'], $names);
	}

	public function testDecodeStoredEntry(): void {
		$data = 'raw image bytes';
		$this->assertSame($data, SkinZipPreview::decodeEntry($data, 0, strlen($data)));
		// compressed size lower than announced is suspicious
		$this->assertNull(SkinZipPreview::decodeEntry(substr($data, 0, 3), 0, strlen($data)));
	}

	public function testDecodeDeflateEntry(): void {
		$deflated = gzdeflate('some payload which is long enough to compress');
		$payload = 'some payload which is long enough to compress';
		$this->assertSame($payload, SkinZipPreview::decodeEntry($deflated, 8, strlen($payload)));
		// output bigger than announced must be refused
		$this->assertNull(SkinZipPreview::decodeEntry($deflated, 8, 5));
		// garbage must be refused
		$this->assertNull(SkinZipPreview::decodeEntry('not deflate data!!', 8, 50));
	}

	public function testDecodeUnknownMethod(): void {
		$this->assertNull(SkinZipPreview::decodeEntry('zz', 12, 2));
	}

	public function testLocalDataOffset(): void {
		$localHeader = "PK\x03\x04"
			. pack('v', 20)
			. pack('v', 0)
			. pack('v', 0)
			. pack('v', 0) . pack('v', 0)
			. pack('V', 0)
			. pack('V', 10)
			. pack('V', 30)
			. pack('v', 12)
			. pack('v', 24)
			. str_repeat('n', 12)
			. str_repeat('e', 24)
			. str_repeat('d', 30);
		$this->assertSame(30 + 12 + 24, SkinZipPreview::localDataOffset($localHeader));
		$this->assertNull(SkinZipPreview::localDataOffset('way too short'));
		$this->assertNull(SkinZipPreview::localDataOffset(str_repeat("\x00", 40)));
	}

	/**
	 * Mimic the range-slice flow of the service on a whole-ZIP buffer:
	 * EOCD probe, central directory, entry selection, extraction, decompression
	 */
	public function testPreviewExtractionFlow(): void {
		$iconData = "\x00\x00\x02\x00" . str_repeat('i', 66); // fake TGA bytes, conversion is out of scope here
		$previewPng = str_repeat('p', 999); // fake preview PNG bytes
		$zip = $this->makeZip([
			'Skin_B.tga' => [str_repeat('s', 40000), 8],
			'big one/ICON.TGA' => [$iconData, 0],
			'someicon.png' => [$previewPng, 8],
		], true);
		$total = strlen($zip);

		$tail = substr($zip, max(0, $total - 65536));
		$eocd = SkinZipPreview::parseEOCD($tail);
		$this->assertIsArray($eocd);

		$centralDirectory = substr($zip, $eocd['cdOffset'], $eocd['cdSize']);
		$entries = SkinZipPreview::filterPreviewEntries(SkinZipPreview::listEntries($centralDirectory));
		$this->assertGreaterThanOrEqual(1, count($entries));
		// the well known Icon.tga comes first, texture of the car skipped, zip within a path handled
		$this->assertSame('big one/ICON.TGA', $entries[0]['name']);

		$selected = $entries[0];
		$data = substr($zip, $selected['localHeaderOffset'], 30 + strlen($selected['name']) + strlen(str_repeat('x', 17)) + $selected['compressedSize']);
		$dataOffset = SkinZipPreview::localDataOffset(substr($data, 0, 30));
		$this->assertIsInt($dataOffset);
		$extracted = substr($data, $dataOffset, $selected['compressedSize']);
		$this->assertSame($iconData, SkinZipPreview::decodeEntry($extracted, $selected['method'], $selected['uncompressedSize']));

		// then the next candidate works as well
		$pngEntry = $entries[1];
		$this->assertSame('someicon.png', $pngEntry['name']);
	}
}
