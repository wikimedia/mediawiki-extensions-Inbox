<?php

namespace Inbox\Models;

use FormatJson;
use MailAddress;
use MediaWiki\MediaWikiServices;
use stdClass;
use Wikimedia\Rdbms\IResultWrapper;

class Email {
	private string $to;
	private string $from;
	private string $timestamp;

	/**
	 * @param array $headers Associative array of headers for the email
	 * @param MailAddress|array $to To address
	 * @param MailAddress $from From address
	 * @param string $subject Subject of the email
	 * @param string $body Body of the message
	 * @param string|null $timestamp
	 */
	public function __construct(
		private readonly array $headers,
		MailAddress|array $to,
		MailAddress $from,
		private readonly string $subject,
		private readonly string $body,
		?string $timestamp = null
	) {
		$this->to = $to[ 0 ]->address;
		$this->from = $from->address;
		$this->timestamp = $timestamp ?: wfTimestampNow();
	}

	public static function getNewestEmailTimestamp( string $emailAddress ): string {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		return $dbr->selectField(
			'inbox_email',
			'email_timestamp',
			[ 'email_to' => $emailAddress ],
			__METHOD__,
			[ 'ORDER BY' => [ 'email_timestamp DESC' ], 'limit' => 1 ]
		);
	}

	/**
	 * Save email to DB
	 */
	public function save(): void {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->insert(
			'inbox_email',
			[
				'email_headers' => FormatJson::encode( $this->headers ),
				'email_to' => $this->to,
				'email_from' => $this->from,
				'email_subject' => $this->subject,
				'email_body' => $this->body,
				'email_timestamp' => $this->timestamp,
			],
			__METHOD__
		);
	}

	public static function getUnreadCount( string $emailAddress ): int {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		return $dbr->selectRowCount(
			'inbox_email',
			'email_to',
			[
				'email_to' => $emailAddress,
				'email_read' => 0,
			],
			__METHOD__
		);
	}

	public static function getAll( string $emailAddress ): IResultWrapper {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		return $dbr->select(
			'inbox_email',
			[ 'email_id', 'email_from', 'email_subject', 'email_timestamp', 'email_read' ],
			[ 'email_to' => $emailAddress ],
			__METHOD__,
			[ 'ORDER BY' => [ 'email_timestamp DESC' ] ]
		);
	}

	public static function get( string $emailAddress, string $id ): stdClass|false {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		return $dbr->selectRow(
			'inbox_email',
			[ 'email_from', 'email_headers', 'email_subject', 'email_body', 'email_timestamp' ],
			[ 'email_id' => $id, 'email_to' => $emailAddress ],
			__METHOD__
		);
	}

	public static function markRead( string $id ): void {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->update(
			'inbox_email',
			[ 'email_read' => 1 ],
			[ 'email_id' => $id ],
			__METHOD__
		);
	}

	public static function markAllRead( string $to ): void {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$dbw->update(
			'inbox_email',
			[ 'email_read' => 1 ],
			[ 'email_to' => $to ],
			__METHOD__
		);
	}
}
