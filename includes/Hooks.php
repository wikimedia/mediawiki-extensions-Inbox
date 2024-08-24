<?php

namespace Inbox;

use Config;
use Inbox\Models\Email;
use MailAddress;
use MediaWiki\Hook\AlternateUserMailerHook;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\OutputPageCheckLastModifiedHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Html\Html;
use OutputPage;
use Skin;
use SkinTemplate;
use SpecialPage;

class Hooks implements
	AlternateUserMailerHook,
	SkinTemplateNavigation__UniversalHook,
	OutputPageCheckLastModifiedHook,
	BeforePageDisplayHook
{

	/** @var Config */
	private $config;

	/**
	 * @param Config $config
	 */
	public function __construct(
		Config $config
	) {
		$this->config = $config;
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
	public function onAlternateUserMailer( $headers, $to, $from, $subject, $body ) {
		$email = new Email( $headers, $to, $from, $subject, $body );
		$email->save();
		if ( $this->config->get( 'InboxSkipRegularEmail' ) ) {
			return false;
		}
	}

	/**
	 * Handler for SkinTemplateNavigation::Universal hook.
	 * Add a "Notifications" item to the user toolbar ('personal URLs').
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation::Universal
	 * @param SkinTemplate $sk
	 * @param array &$links
	 * @phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	 */
	public function onSkinTemplateNavigation__Universal( $sk, &$links ): void {
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
	public function onOutputPageCheckLastModified( &$modifiedTimes, $out ) {
		$user = $out->getUser();
		if ( $user->isRegistered() ) {
			$newestEmailTimestamp = Email::getNewestEmailTimestamp( $user->getEmail() );
			if ( $newestEmailTimestamp ) {
				$modifiedTimes[ 'inbox-newest-email' ] = $newestEmailTimestamp;
			}
		}
	}

	/**
	 * Display big warning message to prevent accidental installation
	 * on production wiki.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $out->getConfig()->get( 'InboxHideProductionWarningBanner' ) ) {
			return;
		}

		if ( $out->getTitle()->getNamespace() === NS_SPECIAL ) {
			return;
		}

		$out->prependHTML( Html::errorBox(
			$out->msg( 'inbox-prod-warning' )->parse(),
			$out->msg( 'inbox' )->text()
		) );
	}

}
