<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Doctrine Session Driver
 *
 * Sample schema:
 *
 *     CREATE TABLE  `sessions` (
 *         `session_id` VARCHAR( 24 ) NOT NULL,
 *         `last_active` INT UNSIGNED NOT NULL,
 *         `contents` TEXT NOT NULL,
 *         PRIMARY KEY ( `session_id` ),
 *         INDEX ( `last_active` )
 *     ) ENGINE = MYISAM ;
 *
 * @author     Flynsarmy (http://www.flynsarmy.com)
 * @version    1.01
 * @license    http://creativecommons.org/licenses/by/3.0/
 */
class Session_Doctrine extends Session {

	// Database instance
	protected $_db;

	/*
	 * Database table name
	 * Note: In doctrine this is the name of the class, not the table name
	 * Note 2: Had to use 'Sessions' instead of 'Session' to stop clash with Kohana Session class
	 */
	protected $_table = 'Sessions';

	// Database column names
	protected $_columns = array(
		'session_id'  => 'session_id',
		'last_active' => 'last_active',
		'contents'    => 'contents'
	);

	// Garbage collection requests
	protected $_gc = 500;

	// The current session id
	protected $_session_id;

	// The old session id
	protected $_update_id;

	public function __construct(array $config = NULL, $id = NULL)
	{
		/*
		// Use the default group
		if ( !isset($config['group']) )
			$config['group'] = 'default';

		// Load the database
		$this->_db = Database::instance($config['group']);
		*/

		// Set the table name
		if (isset($config['table']))
			$this->_table = (string) $config['table'];

		// Set the gc chance
		if (isset($config['gc']))
			$this->_gc = (int) $config['gc'];

		// Overload column names
		if (isset($config['columns']))
			$this->_columns = $config['columns'];

		parent::__construct($config, $id);

		// Run garbage collection
		// This will average out to run once every X requests
		if (mt_rand(0, $this->_gc) === $this->_gc)
			$this->_gc();
	}

	public function id()
	{
		return $this->_session_id;
	}

	protected function _read($id = NULL)
	{
		if ($id OR $id = Cookie::get($this->_name))
		{
			$contents = Doctrine_Query::create()
				->select( $this->_columns['contents'] )
				->from( $this->_table )
				->where( $this->_columns['session_id'].'=?', $id )
				->fetchOne(array(), Doctrine_Core::HYDRATE_SINGLE_SCALAR);

			if ( $contents !== false )
			{
				// Set the current session id
				$this->_session_id = $this->_update_id = $id;

				// Return the contents
				return $contents;
			}
		}

		// Create a new session id
		$this->_regenerate();

		return NULL;
	}

	protected function _regenerate()
	{
		$query = Doctrine_Query::create()
				->select( $this->_columns['session_id'] )
				->from( $this->_table );

		do
		{
			// Create a new session id
			$id = str_replace('.', '-', uniqid(NULL, TRUE));

			$result = $query
				->where( $this->_columns['session_id'].'=?', $id )
				->fetchOne(array(), Doctrine_Core::HYDRATE_SINGLE_SCALAR);
		}
		while( $result !== false );

		return $this->_session_id = $id;
	}

	protected function _write()
	{
		$cols =& $this->_columns;

		if ($this->_update_id === NULL)
		{
			$Session = new $this->_table();
			$Session->$cols['session_id'] = $this->_session_id;
		}
		else
		{
			$Session = Doctrine_Core::getTable( $this->_table )
				->findOneBySession_id( $this->_update_id );

			// Also update the session id
			if ( $this->_update_id !== $this->_session_id )
				$Session->$cols['session_id'] = $this->_session_id;
		}


		$Session->$cols['last_active'] = $this->_data['last_active'];
		$Session->$cols['contents'] = $this->__toString();

		// Execute the query
		$Session->save();

		// The update and the session id are now the same
		$this->_update_id = $this->_session_id;

		// Update the cookie with the new session id
		Cookie::set($this->_name, $this->_session_id, $this->_lifetime);

		return TRUE;
	}

	protected function _destroy()
	{
		// Session has not been created yet
		if ( $this->_update_id === NULL )
			return TRUE;

		// Delete the current session
		Doctrine_Query::create()
			->delete( $this->_table )
			->where( $this->_columns['session_id'].'=?', $this->_update_id )
			->execute();

		try
		{
			// Delete the cookie
			Cookie::delete($this->_name);
		}
		catch ( Exception $e )
		{
			// An error occurred, the session has not been deleted
			return FALSE;
		}

		return TRUE;
	}

	protected function _gc()
	{
		// Expire sessions when their lifetime is up
		if ($this->_lifetime)
			$expires = $this->_lifetime;
		// Expire sessions after one month
		else
			$expires = Date::MONTH;

		// Delete all sessions that have expired
		Doctrine_Query::create()
			->delete( $this->_table )
			->where( $this->_columns['last_active'].' < ?', time()-$expires )
			->execute();
	}
}
