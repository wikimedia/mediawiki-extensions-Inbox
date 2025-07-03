<?php

namespace Inbox\Specials;

use FormatJson;
use Inbox\Models\Email;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\SpecialPage\SpecialPage;
use Profiler;

class SpecialInbox extends SpecialPage {

	public function __construct() {
		parent::__construct( 'Inbox' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'login';
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->requireLogin();
		if ( is_numeric( $par ) ) {
			$this->showEmail( $this->getUser()->getEmail(), $par );
		} else {
			$this->showAllEmails( $this->getUser()->getEmail() );
		}
	}

	/**
	 * @param string $emailAddress
	 * @param string $emailId
	 */
	private function showEmail( $emailAddress, $emailId ) {
		// Ignore warnings about writes on GET when the email is marked as read
		$scope = Profiler::instance()->getTransactionProfiler()->silenceForScope();

		$out = $this->getOutput();
		$email = Email::get( $emailAddress, $emailId );
		if ( $email ) {
			$out->setArticleBodyOnly( true );
			$out->addHTML( htmlspecialchars( $email->email_subject ) );
			$out->addHTML( '<hr />' );
			$headers = array_change_key_case( FormatJson::decode( $email->email_headers, true ) );
			if ( strpos( $headers[ 'content-type' ], 'multipart' ) !== false ) {
				preg_match( '/boundary=\"(.*?)\"/', $headers[ 'content-type' ], $m );
				$boundary = $m[1];
				$parts = explode( '--' . $boundary, $email->email_body );
				// Assume multiparts emails are always using Content-Transfer-Encoding: quoted-printable
				// FIXME: We should probably parse the part headers here
				$parts = array_map( 'quoted_printable_decode', $parts );
				$this->showEmailcontent( $parts[1], true );
				$out->addHTML( '<hr />' );
				$this->showEmailcontent( $parts[2] );
			} else {
				$plaintext = strpos( $headers[ 'content-type' ], 'text/plain' ) >= 0;
				$quotedPrintable = strtolower( $headers[ 'content-transfer-encoding' ] ?? '' ) === 'quoted-printable';
				$body = $email->email_body;
				if ( $quotedPrintable ) {
					$body = quoted_printable_decode( $body );
				}
				$this->showEmailcontent( $body, $plaintext );
			}

			Email::markRead( $emailId );
		} else {
			parent::execute( $emailId );
			$out->addHTML( 'email not found' );
		}
	}

	/**
	 * @param string $content
	 * @param bool $plainText
	 */
	private function showEmailcontent( $content, $plainText = false ) {
		if ( $plainText ) {
			$html = Html::element(
				'pre',
				[],
				$content
			);
		} else {
			$html = Html::rawElement(
				'div',
				[],
				$content
			);
		}
		$this->getOutput()->addHTML( $html );
	}

	/**
	 * @param string $emailAddress
	 */
	private function showAllEmails( $emailAddress ) {
		parent::execute( null );

		// Show button to mark all as read / mark them as read if the button was clicked
		$htmlForm = HTMLForm::factory( 'codex', [
			[
				'type' => 'submit',
				'flags' => [],
				'buttonlabel-message' => 'inbox-mark-all-as-read',
			]
		], $this->getContext() )
			->suppressDefaultSubmit()
			->setSubmitCallback( static function ( $formData, $form ) use ( $emailAddress ) {
				// Ignore warnings about writes on GET when the email is marked as read
				$scope = Profiler::instance()->getTransactionProfiler()->silenceForScope();
				Email::markAllRead( $emailAddress );
				return true;
			} )
			->showAlways();

		$emails = Email::getAll( $emailAddress );
		if ( $emails ) {
			$this->getOutput()->addModuleStyles( [
				'inbox.style',
				'mediawiki.pager.styles',
			] );
			// @phan-suppress-next-line SecurityCheck-XSS
			$this->getOutput()->addHTML( Html::rawElement(
				'table',
				[ 'class' => 'email-all mw-datatable' ],
				'<tr><th>From</th><th>Subject</th><th>Time</th></tr>' .
				implode( '', array_map( function ( $email ) {
					return Html::rawElement(
						'tr',
						[ 'class' => [ !$email->email_read ? 'email-unread' : '', 'email-one' ] ],
						Html::element(
							'td',
							[ 'class' => 'email-from' ],
							$email->email_from
						) .
						Html::rawElement(
							'td',
							[ 'class' => 'email-subject' ],
							Html::element(
								'a',
								[ 'href' => SpecialPage::getTitleFor( 'Inbox', $email->email_id )->getLinkURL() ],
								$email->email_subject
							)
						) .
						Html::element(
							'td',
							[ 'class' => 'email-timestamp' ],
							$this->getLanguage()->userTimeAndDate( $email->email_timestamp, $this->getUser() )
						)
					);
				}, iterator_to_array( $emails, false ) )
			) ) );
		}
	}

}
