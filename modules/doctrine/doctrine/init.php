<?php
	/* Doctrine integration */
	require Kohana::find_file('classes', 'vendor/doctrine/Doctrine');
	spl_autoload_register(array('Doctrine', 'autoload'));
	// Getting kohana configurations for doctrine
	$db = Kohana::config('database');
	// initializing manager
	$Manager = Doctrine_Manager::getInstance();

	// we load our database connections into Doctrine_Manager
	// this loop allows us to use multiple connections later on
	foreach ($db as $connection_name => $dbconf) {
		//PDO doesn't have hostname and database in the connection array. Extract from DSN string
		if ( $dbconf['type'] == 'pdo' )
		{
			preg_match('/host=([^;]+)/', $dbconf['connection']['dsn'], $dbconf['connection']['hostname']);
			preg_match('/dbname=([^;]+)/', $dbconf['connection']['dsn'], $dbconf['connection']['database']);
			$dbconf['connection']['hostname'] = $dbconf['connection']['hostname'][1];
			$dbconf['connection']['database'] = $dbconf['connection']['database'][1];
		}

		$dsn = $dbconf['type'] .
			'://' . $dbconf['connection']['username'] .
			':' . $dbconf['connection']['password'] .
			'@' . $dbconf['connection']['hostname'] .
			'/' . $dbconf['connection']['database'] .
			'?charset=utf8';

		Doctrine_Manager::connection($dsn, $connection_name);
	}

	// telling Doctrine where our models are located
	if ( is_dir(APPPATH.'models') )
	{
		if ( is_dir(APPPATH.'models/generated') )
			Doctrine_Core::loadModels(APPPATH.'models/generated');

		Doctrine::loadModels(APPPATH.'models');
	}


	// (OPTIONAL) CONFIGURATION BELOW

	// this will allow us to use "mutators"
	$Manager->setAttribute(Doctrine_Core::ATTR_AUTO_ACCESSOR_OVERRIDE, true );
	// Automatically free queries
	$Manager->setAttribute(Doctrine_Core::ATTR_AUTO_FREE_QUERY_OBJECTS, true );
	// Enable validation
	//$Manager->setAttribute(Doctrine::ATTR_VALIDATE, Doctrine::VALIDATE_ALL);
	// this sets all table columns to notnull and unsigned (for ints) by default
	$Manager->setAttribute(
		Doctrine_Core::ATTR_DEFAULT_COLUMN_OPTIONS,
		array('notnull' => true, 'unsigned' => true)
	);
	// set the default primary key to be named 'id', integer, 4 bytes
	$Manager->setAttribute(
		Doctrine_Core::ATTR_DEFAULT_IDENTIFIER_OPTIONS,
		array('name' => 'id', 'type' => 'integer', 'length' => 4)
	);
	//We want UTF8
	$Manager->setCollate('utf8_unicode_ci');
	$Manager->setCharset('utf8');

	//Load custom hydrators
	foreach( glob(dirname(__FILE__).'/classes/doctrine/hydrators/DoctrineHydrator_*.php') as $file )
		require_once( $file );

	//Load custom behaviours
	foreach( glob(dirname(__FILE__).'/classes/doctrine/behaviours/*.php') as $file )
		require_once( $file );
