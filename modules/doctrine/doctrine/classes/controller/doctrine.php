<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Doctrine extends Controller
{
	public function before()
	{
		parent::before();

		//Restrict controller to localhost
		if ( !in_array($_SERVER['REMOTE_ADDR'], array('::1', '127.0.0.1')) )
		{
			echo "DENIED!";
			exit;
		}
	}

	function action_index()
	{
		$this->request->response = View::factory('doctrine/doctrine')->render();

		if ( !empty($_POST['schema']) )
		{
			Doctrine_Core::generateModelsFromYaml(
				APPPATH . DIRECTORY_SEPARATOR . 'models/fixtures/schema',
				APPPATH . DIRECTORY_SEPARATOR . 'models',
				array('generateBaseClasses'=>true)
			);
			$this->request->response .= "Done!";
		}
		elseif ( !empty($_POST['tables']) )
		{
			Doctrine::createTablesFromModels();
			$this->request->response .= "Done!";
		}
		elseif ( !empty($_POST['data']) )
		{
 			Doctrine_Manager::connection()->execute("
				SET FOREIGN_KEY_CHECKS = 0
			");

			Doctrine::loadData(APPPATH . DIRECTORY_SEPARATOR . 'models/fixtures/data');
			$this->request->response .= "Done!";
		}
	}
}
