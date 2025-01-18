<?php

declare(strict_types=1);

namespace OCA\Trackmania\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010003Date20250118174046 extends SimpleMigrationStep {

	public function __construct() {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$schemaChanged = false;

		if (!$schema->hasTable('trackmania_trck_pos')) {
			$schemaChanged = true;
			$table = $schema->createTable('trackmania_trck_pos');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('account_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('track_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('track_uid', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('first_seen_at', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('last_seen_at', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('position', Types::INTEGER, [
				'notnull' => true,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id', 'account_id', 'track_id'], 'trackmania_tp_usid_acid_trid');
		}

		return $schemaChanged ? $schema : null;
	}
}
