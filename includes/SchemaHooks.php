<?php

namespace Inbox;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHooks implements LoadExtensionSchemaUpdatesHook {
	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		$baseDir = dirname( __DIR__ );
		$typePath = "$baseDir/sql/$type";

		$updater->addExtensionTable( 'inbox_email', "$typePath/tables-generated.sql" );
	}
}
