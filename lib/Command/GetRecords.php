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

class GetRecords extends Base {

	public function __construct(
		private TrackmaniaAPIService $trackmaniaAPIService,
	) {
		parent::__construct();
	}

	protected function configure() {
		$this->setName('trackmania:get-records')
			->setDescription('Get your records for all the maps you\'ve played')
			->addArgument(
				'user_id',
				InputArgument::REQUIRED,
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$userId = $input->getArgument('user_id');
		$data = $this->trackmaniaAPIService->getAllMapsWithPosition($userId);
		$output->writeln(json_encode($data, JSON_PRETTY_PRINT));
		return 0;
	}
}
