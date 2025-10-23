<?php

namespace Inbox;

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHooks implements LoadExtensionSchemaUpdatesHook {
	/**
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		$baseDir = dirname( __DIR__ );
		$typePath = "$baseDir/sql/$type";

		$updater->addExtensionTable( 'inbox_email', "$typePath/tables-generated.sql" );
	}
}
