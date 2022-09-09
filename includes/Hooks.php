<?php

namespace Inbox;

use DatabaseUpdater;
use Inbox\Models\Email;
use MailAddress;
use OutputPage;
use SkinTemplate;
use SpecialPage;

class Hooks {

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'inbox_email', dirname( __DIR__ ) . "/sql/inbox.sql" );
	}

	/**
	 * @param array $headers Associative array of headers for the email
	 * @param MailAddress|array $to To address
	 * @param MailAddress $from From address
	 * @param string $subject Subject of the email
	 * @param string $body Body of the message
	 * @return bool|string|void True or no return value to continue sending email in the
	 *   regular way, or false to skip the regular method of sending mail. Return a string
	 *   to return a php-mail-error message containing the error.
	 */
	public static function onAlternateUserMailer( $headers, $to, $from, $subject, $body ) {
		$email = new Email( $headers, $to, $from, $subject, $body );
		$email->save();
	}

	/**
	 * Handler for SkinTemplateNavigation::Universal hook.
	 * Add a "Notifications" item to the user toolbar ('personal URLs').
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation::Universal
	 * @param SkinTemplate $sk
	 * @param array &$links
	 */
	public static function onSkinTemplateNavigationUniversal( $sk, &$links ): void {
		$user = $sk->getUser();
		if ( $user->isAnon() || !$user->getEmail() ) {
			return;
		}

		$unreadCount = Email::getUnreadCount( $user->getEmail() );
		$text = 'Inbox';
		if ( $unreadCount ) {
			$text .= " ($unreadCount)";
		}
		$inboxLink = [
			'href' => SpecialPage::getTitleFor( 'Inbox' )->getLinkURL(),
			'text' => $text,
			'class' => 'tbd',
			'icon' => 'message',
		];

		if ( isset( $links['user-menu'] ) ) {
			$links['user-menu'] = wfArrayInsertAfter( $links['user-menu'], [ 'inbox' => $inboxLink ], 'userpage' );
		}
	}

	/**
	 * @param array &$modifiedTimes
	 * @param OutputPage $out
	 */
	public static function onOutputPageCheckLastModified( array &$modifiedTimes, OutputPage $out ) {
		$user = $out->getUser();
		if ( $user->isRegistered() ) {
			$newestEmailTimestamp = Email::getNewestEmailTimestamp( $user->getEmail() );
			if ( $newestEmailTimestamp ) {
				$modifiedTimes[ 'inbox-newest-email' ] = $newestEmailTimestamp;
			}
		}
	}

}
