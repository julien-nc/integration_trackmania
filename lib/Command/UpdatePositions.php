<?php

/**
 * Nextcloud - Trackmania
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Trackmania\Command;

use OC\Core\Command\Base;
use OCA\Trackmania\Service\TrackmaniaAPIService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdatePositions extends Base {

	public function __construct(
		private TrackmaniaAPIService $trackmaniaAPIService,
	) {
		parent::__construct();
	}

	protected function configure() {
		$this->setName('trackmania:update:positions')
			->setDescription('Update positions of one or all user')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL,
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$userId = $input->getArgument('user_id');
		if ($userId) {
			$output->writeln('############### Updating positions for user ' . $userId);
			if (!$this->trackmaniaAPIService->isUserConnected($userId)) {
				$output->writeln('!!!!!! User ' . $userId . ' is not connected to Trackmania');
				return 0;
			}
			$data = $this->trackmaniaAPIService->getAllMapsWithPosition($userId);
			foreach ($data as $i => $item) {
				$output->writeln(
					'[' . $i . '] [' . $userId . '] Updated map "' . ($item['mapInfo']['mapId'] ?? 'unknown ID') . '" '
					. '[' . ($item['bestKnownPosition']['position'] ?? '??')
					. ', '. ($item['bestKnownPosition']['first_seen_at'] ?? '??')
					. ', '. ($item['bestKnownPosition']['last_seen_at'] ?? '??')
					.']'
				);
			}
		} else {
			$data = $this->trackmaniaAPIService->updatePositionsOfConnectedUsers();
			foreach ($data as $i => $item) {
				$output->writeln(
					'[' . $i . '] [' . $item['user_id'] . '] Updated map "' . ($item['map']['mapInfo']['mapId'] ?? 'unknown ID') . '" '
					. '[' . ($item['map']['bestKnownPosition']['position'] ?? '??')
					. ', '. ($item['map']['bestKnownPosition']['first_seen_at'] ?? '??')
					. ', '. ($item['map']['bestKnownPosition']['last_seen_at'] ?? '??')
					.']'
				);
			}
		}
		return 0;
	}
}
