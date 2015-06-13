<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * ORM Doctrine driver.
 *
 * @package    Auth
 * @author     Flynsarmy
 * @copyright  (c) 2009 Flynsarmy
 * @license    Teh FREEZ!! //http://kohanaphp.com/license.html
 */
class Auth_Doctrine extends Auth {

	/**
	 * Checks if a session is active.
	 *
	 * @param   mixed    role name string, role ORM object, or array with role names
	 * @return  boolean
	 */
	public function logged_in($role = NULL)
	{
		$status = FALSE;

		// Get the user from the session
		$user = $this->get_user();

		if (is_object($user) /*AND $user instanceof Model_User AND $user->loaded()*/)
		{
			// Everything is okay so far
			$status = TRUE;

			if ( !empty($role) )
			{
				// Multiple roles to check
				if (is_array($role))
				{
					// Check each role
					foreach ($role as $_role)
					{
						if ( ! is_object($_role))
							$_role = Doctrine_Core::getTable('Role')->findOneByName( $_role );

						if ( !$this->_is_in_db($_role->id, $user->Roles, 'id') )
						//if ( !$user->has('roles', $_role) )
						{
							// Set the status false and get outta here
							$status = FALSE;
							break;
						}
					}
				}
				// Single role to check
				else
				{
					if ( !is_object($role) )
					{
						// Load the role
						$role = Doctrine_Core::getTable('Role')->findOneByName( $role );
						//$role = ORM::factory('role', array('name' => $role));
					}

					// Check that the user has the given role
					//$status = $user->has('roles', $role);
					$status = $this->_is_in_db($role->id, $user->Roles, 'id');
				}
			}
		}

		return $status;
	}


	/**
	 * Logs a user in.
	 *
	 * @param   string   username
	 * @param   string   password
	 * @param   boolean  enable autologin
	 * @return  boolean
	 */
	protected function _login($user, $password, $remember)
	{
		if ( !is_object( $user ) )
			$user = Doctrine_Query::create()
				->from('User u, u.Roles r')
				->addWhere('u.username=?', $user)
				->addWhere('u.password=?', $password)
				->fetchOne();

		if ( $user && $this->_is_in_db('login', $user->Roles, 'name') )
		{
			if ( $remember )
			{
				//Delete old tokens for user
				$user->Tokens->delete();

				// Create a new autologin token
				$token = new UserToken();
				$token = $this->update_token( $token, $user );
				$token->save();

				// Set the autologin cookie
				Cookie::set('authautologin', $token->token, $this->_config['lifetime']);
			}

			// Finish the login
			$this->complete_login( $user );

			return TRUE;
		}

		// Login failed
		return FALSE;
	}

	/**
	 * Forces a user to be logged in, without specifying a password.
	 *
	 * @param   mixed    username string, or user ORM object
	 * @return  boolean
	 */
	public function force_login($user)
	{
		if ( ! is_object($user))
			$user = Doctrine_Query::create()
				->from('User u, u.Roles r')
				->addWhere('u.username=?', $user)
				->fetchOne();

		// Mark the session as forced, to prevent users from changing account information
		$this->_session->set('auth_forced', TRUE);

		// Run the standard completion
		$this->complete_login($user);
	}

	/**
	 * Logs a user in, based on the authautologin cookie.
	 *
	 * @return  mixed
	 */
	public function auto_login()
	{
		if ($token = Cookie::get('authautologin'))
		{
			$this->delete_expired_tokens();

			// Load the token and user
			//$token = ORM::factory('user_token', array('token' => $token));
			$token = Doctrine_Core::getTable('UserToken')->findOneByToken($token);

			//if ($token->loaded() AND $token->user->loaded())
			if ( $token )
			{
				if ($token->user_agent === sha1(Request::$user_agent))
				{
					// Save the token to create a new unique token
					//$token->save();

					// Set the new token
					Cookie::set('authautologin', $token->token, $token->expires - time());

					// Automatic login was successful
					$user = Doctrine_Query::create()
						->from( $token->tbl )
						->addWhere('id=?', $token->user_id)
						->fetchOne();

					// Complete the login with the found data
					$this->complete_login( $user );

					return $user;
				}

				// Token is invalid
				$token->delete();
			}
		}

		return FALSE;
	}

	/**
	 * Gets the currently logged in user from the session (with auto_login check).
	 * Returns FALSE if no user is currently logged in.
	 *
	 * @return  mixed
	 */
	public function get_user()
	{
		$user = parent::get_user();

		if ($user === FALSE)
		{
			// check for "remembered" login
			$user = $this->auto_login();
		}

		return $user;
	}

	/**
	 * Log a user out and remove any autologin cookies.
	 *
	 * @param   boolean  completely destroy the session
	 * @param	boolean  remove all tokens for user
	 * @return  boolean
	 */
	public function logout($destroy = FALSE, $logout_all = FALSE)
	{
		// Set by force_login()
		$this->_session->delete('auth_forced');

		if ($token = Cookie::get('authautologin'))
		{
			// Delete the autologin cookie to prevent re-login
			Cookie::delete('authautologin');

			Doctrine_Query::create()
				->delete('UserToken')
				->where('token=?', $token)
				->execute();

			/*
			// Clear the autologin token from the database
			$token = Doctrine_Core::getTable('UserToken')->findOneByToken( $token );

			if ($token->loaded() AND $logout_all)
			{
				//ORM::factory('user_token')->where('user_id', '=', $token->user_id)->delete_all();
				Doctrine_Query::create()
					->delete('UserToken')
					->where('user_id=?', $token->user_id)
					->execute();
			}
			elseif ($token->loaded())
			{
				$token->delete();
			}
			*/
		}

		return parent::logout($destroy);
	}

	/**
	 * Get the stored password for a username.
	 *
	 * @param   mixed   username string, or user ORM object
	 * @return  string
	 */
	public function password($user)
	{
		if ( !is_object($user))
			$user = Doctrine_Query::create()
				->select('password')
				->from('User u')
				->addWhere('u.username=?', $user)
				->fetchOne();

		return $user ? $user->password : '';
	}

	/**
	 * Complete the login for a user by incrementing the logins and setting
	 * session data: user_id, username, roles.
	 *
	 * @param   object  user ORM object
	 * @return  void
	 */
	protected function complete_login($user)
	{
		//$user->complete_login();

		return parent::complete_login($user);
	}

	/**
	 * Compare password with original (hashed). Works for current (logged in) user
	 *
	 * @param   string  $password
	 * @return  boolean
	 */
	public function check_password($password)
	{
		$user = $this->get_user();

		if ($user === FALSE)
		{
			// nothing to compare
			return FALSE;
		}

		$hash = $this->hash_password($password, $this->find_salt($user->password));

		return $hash == $user->password;
	}

	public function delete_expired_tokens()
	{
		Doctrine_Query::create()
			->delete('UserToken')
			->where('expires<?', time())
			->execute();
	}

	public function update_token( $Token, $User )
	{
		// Set token data
		$Token->User = $User;
		$Token->created = time();
		$Token->expires = time() + $this->_config['lifetime'];
		$Token->user_agent = sha1(Request::$user_agent);
		while ( true )
		{
			$unique_tok = Text::random('alnum', 32);
			if ( !Doctrine_Core::getTable( 'UserToken' )->findOneByToken( $unique_tok ) )
				break;
		}
		$Token->token = $unique_tok;

		return $Token;
	}

	/**
	 * Checks if a specified val is in a doctrine result set
	 *
	 * @param   string  $val
	 * @param   Doctrine_Resultset $db
	 * @param   string  $field
	 * @return  boolean
	 */
	private function _is_in_db($val, $db, $field)
	{
		foreach ( $db as $row )
			if ( $row->$field == $val )
				return true;

		return false;
	}

	/**
	 * Determines if specified user has a specified role
	 *
	 * @param   id       userid
	 * @param   string   role name
	 * @return  boolean
	 */
	public function has_role( $user_id, $role )
	{
		$Role = Doctrine_Core::getTable('Role')->findOneByName( $role );
		if ( !$Role ) return false;

		$result = Doctrine_Query::create()
			->select('*')
			->from('UserRole')
			->addWhere('user_id=?', $user_id)
			->addWhere('role_id=?', $Role->id)
			->execute(array(), Doctrine_Core::HYDRATE_ASSOC);

		return sizeof($result) > 0;
	}
} // End Auth ORM