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
	/** expression lists are chunked to stay below the limit of the databases */
	private const CHUNK_SIZE = 500;

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
	 * Get, for each of the given tracks, the last known position
	 *
	 * @param string $userId
	 * @param string $accountId
	 * @param array $trackIds
	 * @return array<string, TrackPosition> map of trackId to last known position
	 * @throws Exception
	 */
	public function getLastPositionsOfTracks(string $userId, string $accountId, array $trackIds): array {
		$lastPositions = [];
		foreach (array_chunk($trackIds, self::CHUNK_SIZE) as $trackIdsChunk) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->in('track_id', $qb->createNamedParameter($trackIdsChunk, IQueryBuilder::PARAM_STR_ARRAY)))
				// the most recent position of a track comes first
				->orderBy('last_seen_at', 'DESC')
				->addOrderBy('id', 'DESC');

			$result = $qb->executeQuery();
			while ($row = $result->fetch()) {
				$trackId = $row['track_id'];
				if (!isset($lastPositions[$trackId])) {
					$lastPositions[$trackId] = $this->mapRowToEntity($row);
				}
			}
			$result->closeCursor();
		}

		return $lastPositions;
	}

	/**
	 * Get, for each of the given tracks, the best known position ever:
	 * the one with the lowest value, the most recent one taking precedence in case of equality
	 *
	 * @param string $userId
	 * @param string $accountId
	 * @param array $trackIds
	 * @return array<string, TrackPosition> map of trackId to best known position
	 * @throws Exception
	 */
	public function getLastBestPositionsOfTracks(string $userId, string $accountId, array $trackIds): array {
		$bestPositions = [];
		foreach (array_chunk($trackIds, self::CHUNK_SIZE) as $trackIdsChunk) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->in('track_id', $qb->createNamedParameter($trackIdsChunk, IQueryBuilder::PARAM_STR_ARRAY)))
				// the best position of a track comes first
				->orderBy('position', 'ASC')
				->addOrderBy('last_seen_at', 'DESC')
				->addOrderBy('id', 'DESC');

			$result = $qb->executeQuery();
			while ($row = $result->fetch()) {
				$trackId = $row['track_id'];
				if (!isset($bestPositions[$trackId])) {
					$bestPositions[$trackId] = $this->mapRowToEntity($row);
				}
			}
			$result->closeCursor();
		}

		return $bestPositions;
	}

	/**
	 * For each of the given tracks, add a new position row if it is different from the last known one,
	 * or refresh the last seen date of the last known row
	 *
	 * @param string $userId
	 * @param string $accountId
	 * @param array $positionsByTrackId map of trackId to array{trackUid: string, position: int}
	 * @return void
	 * @throws Exception
	 */
	public function updatePositionsOfTracks(string $userId, string $accountId, array $positionsByTrackId): void {
		if (count($positionsByTrackId) === 0) {
			return;
		}

		$ts = time();
		$touchedRowIds = [];
		$rowsToInsert = [];
		foreach (array_chunk($positionsByTrackId, self::CHUNK_SIZE, true) as $positionsChunk) {
			$lastPositionsByTrackId = $this->getLastPositionsOfTracks($userId, $accountId, array_keys($positionsChunk));

			foreach ($positionsChunk as $trackId => $update) {
				$lastPosition = $lastPositionsByTrackId[$trackId] ?? null;
				if ($lastPosition === null || (int)$lastPosition->getPosition() !== $update['position']) {
					$rowsToInsert[] = [$userId, $accountId, $trackId, $update['trackUid'], $ts, $ts, $update['position']];
				} else {
					$touchedRowIds[] = $lastPosition->getId();
				}
			}
		}

		// refresh the last seen date of the rows whose position did not change
		if (count($touchedRowIds) > 0) {
			foreach (array_chunk($touchedRowIds, self::CHUNK_SIZE) as $touchedRowIdsChunk) {
				$qb = $this->db->getQueryBuilder();
				$qb->update($this->getTableName())
					->set('last_seen_at', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT))
					->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
					->andWhere($qb->expr()->eq('account_id', $qb->createNamedParameter($accountId, IQueryBuilder::PARAM_STR)))
					->andWhere($qb->expr()->in('id', $qb->createNamedParameter($touchedRowIdsChunk, IQueryBuilder::PARAM_INT_ARRAY)));
				$qb->executeStatement();
			}
		}

		// create new rows for the positions that changed
		if (count($rowsToInsert) > 0) {
			// rows are inserted in chunks to respect the parameters limit of the databases
			foreach (array_chunk($rowsToInsert, 500) as $rowsChunk) {
				$values = [];
				$params = [];
				foreach ($rowsChunk as $row) {
					$values[] = '(?, ?, ?, ?, ?, ?, ?)';
					array_push($params, ...$row);
				}
				$sql = 'INSERT INTO *PREFIX*' . $this->getTableName() . ' '
					. '(user_id, account_id, track_id, track_uid, first_seen_at, last_seen_at, position) '
					. 'VALUES ' . implode(', ', $values);
				$this->db->executeStatement($sql, $params);
			}
		}
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
		return array_map(static function (array $result) {
			return $result['userid'];
		}, $result->fetchAll());
	}
}
