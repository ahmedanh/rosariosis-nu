<?php
/**
 * Latch System (One click batch runner)
 *
 * @package RosarioSIS
 */

require_once 'modules/School_Setup/includes/Rollover.fnc.php';
require_once 'modules/School_Setup/includes/DatabaseBackup.fnc.php';

DrawHeader( ProgramTitle() );

$next_syear = UserSyear() + 1;

if ( $_REQUEST['modfunc'] === 'latch' )
{
	if ( AllowEdit() )
	{
		// 1. Lock System
		Config( 'MAINTENANCE_MODE', 'Y' );
		$note[] = _( 'Maintenance Mode enabled.' );

		// 2. Backup Database
		$backup_dir = 'assets/FileUploads/Backup/';
		if ( ! is_dir( $backup_dir ) )
		{
			if ( ! mkdir( $backup_dir, 0755, true ) )
			{
				$error[] = sprintf( _( 'Could not create backup directory: %s' ), $backup_dir );
			}
			else
			{
				// Secure the directory
				file_put_contents( $backup_dir . '.htaccess', "Order Deny,Allow\nDeny from all" );
			}
		}

		if ( ! $error )
		{
			$backup_file = $backup_dir . Config( 'NAME' ) . '_Latch_Backup_' . date( 'Y-m-d_H-i-s' ) . '.sql';

			if ( DatabaseBackupSQL( 'save', $backup_file ) )
			{
				$note[] = sprintf( _( 'Database backup saved to: %s' ), $backup_file );
			}
			else
			{
				$error[] = _( 'Database backup failed.' );
			}
		}

		if ( ! $error )
		{
			// 3. Rollover

			// Force Course Periods rollover
			$_REQUEST['course_periods'] = 'Y';

			$tables_list = RolloverGetTables( $next_syear );
			// Simulate $_REQUEST['tables'] structure (['table_name' => 'Y'])
			$tables_to_roll = array_fill_keys( array_keys( $tables_list ), 'Y' );
			$_REQUEST['tables'] = $tables_to_roll;

			// Fix SQL error foreign keys: Process tables in reverse order.
			$tables_reverse = array_reverse( $_REQUEST['tables'] );

			foreach ( (array) $tables_reverse as $table => $value )
			{
				Rollover( $table, 'delete' );
			}

			foreach ( (array) $_REQUEST['tables'] as $table => $value )
			{
				Rollover( $table, 'insert' );
			}

			do_action( 'School_Setup/Rollover.php|rollover_after' );

			$note[] = _( 'Rollover complete.' );

			// 4. Update Default Syear
			if ( RolloverUpdateDefaultSyear( $next_syear ) )
			{
				$note[] = _( 'Default School Year updated.' );
			}
			else
			{
				$error[] = _( 'Could not update Default School Year in config.inc.php.' );
			}
		}

		echo ErrorMessage( $note, 'note' );
		echo ErrorMessage( $error );
	}
}

if ( ! $_REQUEST['modfunc'] )
{
	echo '<form action="' . URLEscape( 'Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=latch' ) . '" method="POST">';
	PopTable( 'header', _( 'Latch System' ) );

	echo _( 'This operation will perform the following actions:' ) . '<br />';
	echo '<ul>';
	echo '<li>' . _( 'Enable Maintenance Mode (System Lock)' ) . '</li>';
	echo '<li>' . _( 'Backup Database' ) . '</li>';
	echo '<li>' . sprintf( _( 'Rollover all data to %s school year' ), FormatSyear( $next_syear, Config( 'SCHOOL_SYEAR_OVER_2_YEARS' ) ) ) . '</li>';
	echo '<li>' . _( 'Update Default School Year' ) . '</li>';
	echo '</ul>';

	echo '<br />';
	echo _( 'Are you sure you want to proceed?' );
	echo '<br /><br />';

	echo '<div class="center">' . SubmitButton( _( 'Latch System' ) ) . '</div>';

	PopTable( 'footer' );
	echo '</form>';
}
