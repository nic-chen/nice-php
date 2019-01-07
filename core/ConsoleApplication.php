<?php

/*
* @author: 4061470@qq.com
*/

class ConsoleApplication extends Application
{
	private $_commander;
	private $_scriptName;
	private $_commanderPath;

	protected function init($config)
	{
		parent::init($config);
		if(!isset($_SERVER['argv'])) // || strncasecmp(php_sapi_name(),'cli',3))
			die('This script must be run from the command line.');
	}

	//执行
	public function processRequest()
	{
		$args = $this->parseArgv();
		if(is_object($commander = $this->loadRunner($this->_commander))) {
			$commander->run($args);
		} else {
			throw new CoreException('Unable to resolve the request:'.$this->_commander.'/'.$this->_action);
		}

	}

	//解析参数
	private function parseArgv()
	{
		$args = $_SERVER['argv'];

		$this->_scriptName = $args[0];
		array_shift($args);

		if(isset($args[0])){
			$this->_commander = $args[0];
			array_shift($args);
		}else{
			$this->_commander = 'help';
		}

		return $args;		
	}

	//加载脚本
	private function loadRunner($commander_name) 
	{
		$commander_class_name = ucfirst($commander_name) . 'Command';
		
		$commander_path       = $this->getCommanderPath();
		$commander_class_file = $commander_path.DIRECTORY_SEPARATOR.$commander_class_name.'.php';

		if(is_file($commander_class_file)){
			if(!class_exists($commander_class_name,false)){
				include($commander_class_file);
			}
			return new $commander_class_name();
		}

		return null;
	}

	public function getCommanderPath()
	{
		if($this->_commanderPath!==null)
			return $this->_commanderPath;
		else
			return $this->_commanderPath = $this->getBasePath().DIRECTORY_SEPARATOR.'source'.DIRECTORY_SEPARATOR.'command';
	}

	public function getCommander()
	{
		return $this->_commander;
	}


}
