<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Trackmania\Db;

use OCA\Trackmania\AppInfo\Application;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Class TrackPositionMapper
 *
 * @package OCA\Trackmania\Db
 *
 * @template-extends QBMapper<TrackPosition>
 */
class TrackPositionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'trackmania_trck_pos', TrackPosition::class);
	}

	/**
	 * @param int $id
	 * @return TrackPosition
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getById(int $id): TrackPosition {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @param string $accountId
	 * @param string $trackId
	 * @return list<TrackPosition>
	 * @throws Exception
	 */
	public function getPositionsOfTrack(string $userId, string $accountId, string $trackId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('track_id', $qb->createNamedParameter($trackId, IQueryBuilder::PARAM_STR)))
			->orderBy('last_seen_at', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 * @param string $accountId
	 * @param string $trackId
	 * @return TrackPosition
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getLastPositionOfTrack(string $userId, string $accountId, string $trackId): TrackPosition {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('track_id', $qb->createNamedParameter($trackId, IQueryBuilder::PARAM_STR)))
			->orderBy('last_seen_at', 'DESC')
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @param string $accountId
	 * @param string $trackId
	 * @return TrackPosition
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getLastBestPositionOfTrack(string $userId, string $accountId, string $trackId): TrackPosition {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('track_id', $qb->createNamedParameter($trackId, IQueryBuilder::PARAM_STR)))
			//->orderBy('position DESC, last_seen_at DESC')
			->orderBy('position', 'ASC')
			->addOrderBy('last_seen_at', 'DESC')
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @param string $accountId
	 * @param string $trackId
	 * @param string $trackUid
	 * @param int $position
	 * @return TrackPosition
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function updatePositionOfTrack(string $userId, string $accountId, string $trackId, string $trackUid, int $position): TrackPosition {
		$ts = time();
		try {
			$lastKnownPosition = $this->getLastPositionOfTrack($userId, $accountId, $trackId);
		} catch (DoesNotExistException $e) {
			$lastKnownPosition = null;
		}

		// if there is no position yet OR if the new position is different from the last know one:
		// create a new position
		if ($lastKnownPosition === null || $position !== $lastKnownPosition->getPosition()) {
			$newPosition = new TrackPosition();
			$newPosition->setUserId($userId);
			$newPosition->setAccountId($accountId);
			$newPosition->setTrackId($trackId);
			$newPosition->setTrackUid($trackUid);
			$newPosition->setFirstSeenAt($ts);
			$newPosition->setLastSeenAt($ts);
			$newPosition->setPosition($position);
			return $this->insert($newPosition);
		}

		// we know the position is still the same
		// just update the last seen time of this position
		$lastKnownPosition->setLastSeenAt($ts);
		return $this->update($lastKnownPosition);
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function getConnectedUserIds(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('userid')
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter('core_token', IQueryBuilder::PARAM_STR)));

		$result = $qb->executeQuery();
		return array_map(static function(array $result) {
			return $result['userid'];
		}, $result->fetchAll());
	}
}
