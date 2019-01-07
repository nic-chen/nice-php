<?php

/*
* @author: 4061470@qq.com
*/

class Session extends Component
{
	
	public $autoStart = true;

	public function __construct($config=null)
	{
		$this->init();
	}
	
	public function init()
	{
		parent::init();
		if($this->autoStart)
			$this->open();
		register_shutdown_function(array($this,'close'));
	}


	public function open()
	{
		if($this->getUseCustomStorage()){
			@session_set_save_handler(array($this,'openSession'),array($this,'closeSession'),array($this,'readSession'),array($this,'writeSession'),array($this,'destroySession'),array($this,'gcSession'));
		}

		@session_start();

	}

	public function get($key, $defaultValue=null)
	{
		return isset($_SESSION[$key]) ? $_SESSION[$key] : $defaultValue;
	}	

	public function set($key, $value, $close=true)
	{
		$_SESSION[$key] = $value;
		if($close){
			$this->close();
		}
	}

	public function close()
	{
		if(session_id()!=='')
			@session_write_close();
	}

	public function regenerateID($deleteOldSession=false)
	{
		session_regenerate_id($deleteOldSession);
	}	

	public function getSessionID()
	{
		return session_id();
	}	

	public function destroy()
	{
		if(session_id()!=='')
		{
			@session_unset();
			@session_destroy();
		}
	}

	public function remove($key, $close=true)
	{
		if(isset($_SESSION[$key]))
		{
			$value = $_SESSION[$key];
			unset($_SESSION[$key]);
			if($close){
				$this->close();
			}

			return $value;
		}
		else
			return null;
	}

	public function clear($close=true)
	{
		foreach($_SESSION as $key=>$val)
			unset($_SESSION[$key]);

		if($close){
			$this->close();
		}

	}

	public function getUseCustomStorage()
	{
		return false;
	}

}
