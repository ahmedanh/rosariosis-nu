<?php

require_once 'modules/School_Setup/includes/DatabaseBackup.fnc.php';

if ( $_REQUEST['modfunc'] !== 'backup' )
{
	Drawheader( ProgramTitle() );
}

if ( $_REQUEST['modfunc'] === 'backup'
	&& isset( $_REQUEST['_ROSARIO_PDF'] ) )
{
	DatabaseBackupSQL( 'download' );

	exit;
}

if ( ! $_REQUEST['modfunc'] )
{
	echo '<br />';
	PopTable( 'header', _( 'Database Backup' ) );
	echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=backup&_ROSARIO_PDF=true' ) . '" method="POST">';
	echo '<br />';
	echo _( 'Download backup files periodically in case of system failure.' );
	echo '<br /><br />';
	echo '<div class="center">' . SubmitButton( _( 'Download Backup File' ) ) . '</div>';
	echo '</form>';
	PopTable( 'footer' );
}
