<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Trackmania\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setAccountId(string $accountId)
 * @method string getAccountId()
 * @method void setTrackId(string $trackId)
 * @method string getTrackId()
 * @method void setTrackUid(string $trackUid)
 * @method string getTrackUid()
 * @method void setFirstSeenAt(int $firstSeenAt)
 * @method int getFirstSeenAt()
 * @method void setLastSeenAt(int $lastSeenAt)
 * @method int getLastSeenAt()
 * @method void setPosition(int $position)
 * @method int getPosition()
 */
class TrackPosition extends Entity implements \JsonSerializable {

	protected $userId;
	protected $accountId;
	protected $trackId;
	protected $trackUid;
	protected $firstSeenAt;
	protected $lastSeenAt;
	protected $position;

	public function __construct() {
		$this->addType('user_id', 'string');
		$this->addType('account_id', 'string');
		$this->addType('track_id', 'string');
		$this->addType('track_uid', 'string');
		$this->addType('first_seen_at', 'integer');
		$this->addType('last_seen_at', 'integer');
		$this->addType('position', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'user_id' => $this->getUserId(),
			'account_id' => $this->getAccountId(),
			'track_id' => $this->getTrackId(),
			'track_uid' => $this->getTrackUid(),
			'first_seen_at' => $this->getFirstSeenAt(),
			'last_seen_at' => $this->getLastSeenAt(),
			'position' => $this->getPosition(),
		];
	}
}
