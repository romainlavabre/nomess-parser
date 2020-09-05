<?php
declare( strict_types=1 );

error_reporting( E_ALL );
ini_set( 'display_errors', 'on' );
ini_set( "log_errors", "1" );
set_time_limit( 0 );
ignore_user_abort( TRUE );


define( 'ROOT', str_replace( 'public/index.php', '', $_SERVER['SCRIPT_FILENAME'] ) );
define( 'NOMESS_CONTEXT', 'DEV' );
ini_set( 'error_log', ROOT . 'var/log/error.log' );


require( ROOT . 'vendor/autoload.php' );
require( ROOT . 'vendor/nomess/kernel/Exception/NomessException.php' );

( new Nomess\Initiator\Initiator() )->initializer();

