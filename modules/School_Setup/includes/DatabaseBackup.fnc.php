<?php
/**
 * Database Backup functions
 *
 * @package RosarioSIS
 */

/**
 * Database Backup SQL
 *
 * @since 12.8
 *
 * @param  string $mode     'download' or 'save'. Defaults to 'download'.
 * @param  string $filepath File path to save backup to (if mode is 'save').
 *
 * @return bool True if successful, false otherwise (outputs error in download mode).
 */
function DatabaseBackupSQL( $mode = 'download', $filepath = '' )
{
	global $DatabaseDumpPath,
		$pg_dumpPath,
		$DatabaseType,
		$DatabaseServer,
		$DatabaseName,
		$DatabaseUsername,
		$DatabasePassword,
		$DatabasePort;

	if ( ! empty( $pg_dumpPath )
		&& empty( $DatabaseDumpPath )
		&& $DatabaseType === 'postgresql' )
	{
		// @since 10.0 Rename $pg_dumpPath configuration variable to $DatabaseDumpPath
		$DatabaseDumpPath = $pg_dumpPath;
	}

	if ( ! isset( $DatabaseDumpPath ) )
	{
		$DatabaseDumpPath = '';
	}

	$exe = escapeshellcmd( $DatabaseDumpPath );

	// Obtain the dump utility version number and check if the path is good.
	$version = [];
	preg_match( "/(\d+(?:\.\d+)?)(?:\.\d+)?.*$/", exec( $exe . " --version" ), $version );

	if ( empty( $version ) )
	{
		$error[] = sprintf( 'The path to the database dump utility specified in the configuration file (config.inc.php) is wrong! (%s)', $DatabaseDumpPath );

		ErrorMessage( $error, 'fatal' );

		return false;
	}

	if ( $DatabaseType === 'postgresql' )
	{
		// Code inspired by phpPgAdmin.
		putenv( 'PGHOST=' . $DatabaseServer );
		putenv( 'PGDATABASE=' . $DatabaseName );
		putenv( 'PGUSER=' . $DatabaseUsername );
		putenv( 'PGPASSWORD=' . $DatabasePassword );

		if ( ! empty( $DatabasePort ) )
		{
			putenv( 'PGPORT=' . $DatabasePort );
		}

		// Build command for executing pg_dump. '--inserts' means dump data as INSERT commands (rather than COPY).
		$cmd = $exe . ' --inserts';
	}
	else
	{
		// @since 10.0 Build command for executing mysqldump.
		// @since 11.3 MySQL dump: export procedures, functions and triggers in a single transaction
		$cmd = $exe . ' --user=' . escapeshellarg( $DatabaseUsername ) .
			' --password=' . escapeshellarg( $DatabasePassword ) .
			' --host=' . escapeshellarg( $DatabaseServer ) .
			( ! empty( $DatabasePort ) ? ' --port=' . escapeshellarg( $DatabasePort ) : '' ) .
			' --routines --triggers --single-transaction' .
			' ' . escapeshellarg( $DatabaseName );
	}

	if ( $mode === 'save' && $filepath )
	{
		$cmd .= ' > ' . escapeshellarg( $filepath );

		exec( $cmd, $output, $return_var );

		if ( $return_var !== 0 )
		{
			return false;
		}

		return true;
	}
	else
	{
		$ctype = "application/force-download";

		header( "Pragma: public" );
		header( "Expires: 0" );
		header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
		header( "Cache-Control: public" );
		header( "Content-Description: File Transfer" );
		header( "Content-Type: $ctype" );

		// Fix download backup filename when contains spaces: use double quotes.
		$filename = '"' . Config( 'NAME' ) . '_database_backup_' . date( 'Y.m.d' ) . '.sql"';

		$header = "Content-Disposition: attachment; filename=" . $filename . ";";

		header( $header );
		header( "Content-Transfer-Encoding: binary" );

		// Execute command and return the output to the screen.
		passthru( $cmd );

		return true;
	}
}
