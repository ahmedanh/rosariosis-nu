#!/usr/bin/env php
<?php
/**
 * Latch CLI Runner
 *
 * One click batch runner that latches the system from A to Z.
 *
 * Actions:
 * 1. Enable Maintenance Mode (System Lock).
 * 2. Backup Database.
 * 3. Rollover all data for all schools.
 * 4. Update Default School Year.
 *
 * @package RosarioSIS
 */

// Check CLI.
if ( php_sapi_name() !== 'cli' )
{
	die( 'This script must be run from the command line.' );
}

// Load RosarioSIS.
require_once 'Warehouse.php';

// Force Admin user for permissions.
// Find an admin user.
$admin_id = DBGetOne( "SELECT STAFF_ID FROM staff WHERE PROFILE='admin' LIMIT 1" );

if ( ! $admin_id )
{
	die( "Error: No Administrator account found.\n" );
}

$_SESSION['STAFF_ID'] = $admin_id;

// Load functions.
require_once 'modules/School_Setup/includes/Rollover.fnc.php';
require_once 'modules/School_Setup/includes/DatabaseBackup.fnc.php';

echo "RosarioSIS Latch System\n";
echo "=======================\n";

// 1. Enable Maintenance Mode.
echo "[1/4] Enabling Maintenance Mode... ";
Config( 'MAINTENANCE_MODE', 'Y' );
echo "Done.\n";

// 2. Backup Database.
echo "[2/4] Backing up Database... ";
$backup_dir = 'assets/FileUploads/Backup/';
if ( ! is_dir( $backup_dir ) )
{
	if ( ! mkdir( $backup_dir, 0755, true ) )
	{
		echo "Failed to create backup directory.\n";
		exit( 1 );
	}
	else
	{
		file_put_contents( $backup_dir . '.htaccess', "Order Deny,Allow\nDeny from all" );
	}
}

$backup_file = $backup_dir . Config( 'NAME' ) . '_Latch_Backup_' . date( 'Y-m-d_H-i-s' ) . '.sql';

if ( DatabaseBackupSQL( 'save', $backup_file ) )
{
	echo "Saved to $backup_file\n";
}
else
{
	echo "Backup Failed!\n";
	exit( 1 );
}

// 3. Rollover.
echo "[3/4] Rolling over data... \n";

// Get Next School Year.
// Note: We use the *default* Syear to determine next year, assuming we are rolling over from the current default.
// Or we can use the UserSyear() which we haven't set yet.
// Config('SYEAR') gives the default school year from DB/Config.

$current_syear = Config( 'SYEAR' );
$next_syear = $current_syear + 1;

$_SESSION['UserSyear'] = $current_syear;

echo "    Current School Year: $current_syear\n";
echo "    Next School Year:    $next_syear\n";

// Get All Schools.
$schools = DBGet( "SELECT ID, TITLE FROM schools WHERE SYEAR='" . $current_syear . "'" );

foreach ( $schools as $school )
{
	$school_id = $school['ID'];
	$school_title = $school['TITLE'];

	echo "    > Processing School: $school_title (ID: $school_id)... ";

	// Set Context.
	$_SESSION['UserSchool'] = $school_id;

	// Force Course Periods rollover.
	$_REQUEST['course_periods'] = 'Y';

	// Get Tables.
	$tables_list = RolloverGetTables( $next_syear );
	$tables_to_roll = array_fill_keys( array_keys( $tables_list ), 'Y' );
	$_REQUEST['tables'] = $tables_to_roll;

	// Reverse order for delete.
	$tables_reverse = array_reverse( $_REQUEST['tables'] );

	foreach ( (array) $tables_reverse as $table => $value )
	{
		Rollover( $table, 'delete' );
	}

	// Insert.
	foreach ( (array) $_REQUEST['tables'] as $table => $value )
	{
		Rollover( $table, 'insert' );
	}

	// Hook?
	// do_action( 'School_Setup/Rollover.php|rollover_after' );
	// This might fail if plugins rely on web context or other global vars, but let's try.
	// We didn't include the file defining the hook, we are running standalone.
	// But Warehouse.php includes functions/Actions.php.

	echo "Done.\n";
}

// 4. Update Default Syear.
echo "[4/4] Updating Default School Year... ";

if ( RolloverUpdateDefaultSyear( $next_syear ) )
{
	echo "Updated to $next_syear.\n";
}
else
{
	echo "Failed. Check config.inc.php permissions.\n";
	// Do not exit, the data is already rolled.
}

echo "=======================\n";
echo "Latch System Complete.\n";
