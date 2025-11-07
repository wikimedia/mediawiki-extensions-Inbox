<?php

namespace Inbox;

use Config;
use Inbox\Models\Email;
use MediaWiki\Hook\AlternateUserMailerHook;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\OutputPageCheckLastModifiedHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Html\Html;
use SpecialPage;

class Hooks implements
	AlternateUserMailerHook,
	SkinTemplateNavigation__UniversalHook,
	OutputPageCheckLastModifiedHook,
	BeforePageDisplayHook
{

	public function __construct(
		private readonly Config $config
	) {
	}

	/** @inheritDoc */
	public function onAlternateUserMailer( $headers, $to, $from, $subject, $body ) {
		$email = new Email( $headers, $to, $from, $subject, $body );
		$email->save();
		if ( $this->config->get( 'InboxSkipRegularEmail' ) ) {
			return false;
		}
	}

	/** @inheritDoc */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$user = $sktemplate->getUser();
		if ( $user->isAnon() || !$user->getEmail() ) {
			return;
		}

		$unreadCount = Email::getUnreadCount( $user->getEmail() );
		// TODO: i18n
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
			if ( isset( $links['user-menu']['userpage'] ) ) {
				$links['user-menu'] = wfArrayInsertAfter( $links['user-menu'], [ 'inbox' => $inboxLink ], 'userpage' );
			} else {
				// If the link to userpage is missing, insert our link at the start.
				$links['user-menu'] = [ 'inbox' => $inboxLink ] + $links['user-menu'];
			}
		}
	}

	/** @inheritDoc */
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
	 * Displays a big warning message to prevent accidental installation
	 * on production wiki.
	 *
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if (
			$out->getConfig()->get( 'InboxHideProductionWarningBanner' ) ||
			$out->getTitle()?->getNamespace() === NS_SPECIAL
		) {
			return;
		}

		$out->prependHTML( Html::errorBox(
			$out->msg( 'inbox-prod-warning' )->parse(),
			$out->msg( 'inbox' )->text()
		) );
	}

}
