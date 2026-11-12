<?php

namespace Telepedia\Extensions\Beacon;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use Telepedia\Extensions\Beacon\Jobs\BeaconTrimNotificationsJob;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat;

class NotificationPurger {

	public const array CONSTRUCTOR_OPTIONS = [
		'BeaconMaxNotificationAge',
		'BeaconMaxNotificationsPerUser',
	];

	/**
	 * The value that turns a limit off
	 * We ignore any other negative number, 0, and any non-whole positive integer
	 * @var int
	 */
	private const int DISABLED = -1;

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly NotificationStore $store,
		private readonly JobQueueGroup $jobQueueGroup,
		private readonly LoggerInterface $logger,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Delete one batch of notifications older than $wgBeaconMaxNotificationAge
	 * @param int $batchSize
	 * @return int the number of rows deleted or 0 if none remain or the limit is off
	 */
	public function purgeExpired( int $batchSize ): int {
		$days = $this->getLimit( 'BeaconMaxNotificationAge' );
		if ( $days === null ) {
			return 0;
		}

		$cutoff = ConvertibleTimestamp::convert(
			TimestampFormat::MW,
			ConvertibleTimestamp::time() - $days * 86400
		);

		return $this->store->deleteOlderThan( $cutoff, $batchSize );
	}

	/**
	 * Delete one batch of the user's notifications beyond the newest $wgBeaconMaxNotificationsPerUser
	 * @param UserIdentity $user
	 * @param int $batchSize
	 * @return int the number of rows deleted, or 0 once they are within the limit, or it is disabled
	 */
	public function trimUser( UserIdentity $user, int $batchSize ): int {
		$keep = $this->getLimit( 'BeaconMaxNotificationsPerUser' );
		if ( $keep === null ) {
			return 0;
		}

		return $this->store->deleteBeyondNewest( $user, $keep, $batchSize );
	}

	/**
	 * Queue a job to bring these users back within $wgBeaconMaxNotificationsPerUser. Runs immediately after they were sent a
	 * notification in a job
	 * @param array $users local users on this wiki
	 * @return void
	 */
	public function queueTrim( array $users ): void {
		if ( !$users || $this->getLimit( 'BeaconMaxNotificationsPerUser' ) === null ) {
			return;
		}

		$this->jobQueueGroup->lazyPush( new JobSpecification(
			BeaconTrimNotificationsJob::JOB_NAME,
			[ 'users' => array_values( array_map( static fn ( UserIdentity $user ) => $user->getId(), $users ) ) ],
			[ 'removeDuplicates' => true ]
		) );
	}

	/**
	 * @param string $name the config setting
	 * @return int|null the limit, or null if it is disabled
	 */
	private function getLimit( string $name ): ?int {
		$value = $this->options->get( $name );
		if ( $value === self::DISABLED ) {
			return null;
		}

		if ( !is_int( $value ) || $value < 1 ) {
			$this->logger->warning( 'Not purging Beacon notifications: ${setting} must be -1 or a whole positive ' .
				'number, got {value}', [
				'setting' => "wg$name",
				'value' => var_export( $value, true ),
			] );
			return null;
		}

		return $value;
	}
}
