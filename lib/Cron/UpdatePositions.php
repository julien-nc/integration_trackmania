<?php

namespace OCA\Trackmania\Cron;

use OCA\Trackmania\Service\TrackmaniaAPIService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

class UpdatePositions extends TimedJob {

	public function __construct(
		ITimeFactory $time,
		private TrackmaniaAPIService $trackmaniaAPIService,
	) {
		parent::__construct($time);
		// Run twice a day, every 12 hours
		$this->setInterval(12 * 60 * 60);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	/**
	 * @param $argument
	 * @return void
	 */
	protected function run($argument): void {
		iterator_to_array($this->trackmaniaAPIService->updatePositionsOfConnectedUsers());
	}
}
