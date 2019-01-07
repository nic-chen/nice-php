<?php

/*
* @author: 4061470@qq.com
*/

class Application
{
	//from config
	private $_view_path;
	private $_runtime_path;
	private $_components         = array();	
	private $_time_zone          = 'Asia/Chongqing';
	
	private $_controller         = 'index';
	private $_action             = 'index';
	private $_loaded_controller  = array();
	private $_created_components = array();
	private $_componentConfig    = array();

	public function __construct($config=null)
	{
		$this->init($config);
	}

	protected function init($config)
	{
		$this->configure($config);
		$this->setTimeZone();
		$this->setComponents();
	}

	private function configure($config)
	{
		if(is_array($config))
		{
			foreach($config as $key=>$value){
				$property        = '_'.$key;
				$this->$property = $value;
			}
		}
	}	
	
	public function run()
	{
		if(Nice::event()->hasEventHandler('onBeginRequest', $this)){
			Nice::event()->raiseEvent('onBeginRequest', $this);
		}
		$this->processRequest();
		if(Nice::event()->hasEventHandler('onEndRequest', $this)){
			Nice::event()->raiseEvent('onEndRequest', $this);;
		}
	}

	public function processRequest()
	{
		$this->parseURI();

		if (is_object($controller = $this->loadController($this->_controller))) {
			//$real_action = 'action'.ucfirst($this->_action);
			//call_user_func_array(array($controller, $real_action), array());
			
			// 只允许访问公有方法
			$reflection = new ReflectionMethod($controller, $this->_action);
		    if (!$reflection->isPublic()) {
		        throw new CoreException("The called method is not public.");
		    }
			$controller->run($this->_action);
		} else {
			throw new CoreException('Unable to resolve the request:'.$this->_controller.'/'.$this->_action);
		}

	}

	private function parseURI() 
	{
        if(isset($_GET['s']) && $_GET['s'] && strpos($_GET['s'], '/') !== false){
            $url = explode('/', ltrim($_GET['s'], '/'));
            //得到控制器
            if(isset($url[0])){
                $this->_controller = $url[0];
                unset($url[0]);
            }
            //得到方法名
            if(isset($url[1])){
                $this->_action = $url[1];
                unset($url[1]);
            } else {
            	$this->_action = $this->getComponent('Request')->getParam('action', 'index');
            }

            //判断是否有其他参数
            if(isset($url)){
                $params = array_values($url);
                // 为了防止URL过长，最多处理20个参数
                if (count($params) > 40) {
                	throw new CoreException('Params counts to long.');
                }
                foreach ($params as $key => $value) {
                	if ($key % 2 === 0) {
                		if (isset($_POST[$value])) {
                			$_GET[$value] = isset($params[$key + 1]) ? $params[$key + 1] : '';
                		} else {
                			$_POST[$value] = isset($params[$key + 1]) ? $params[$key + 1] : '';
                		}
                	}
                }
            }
            unset($_GET['s']);
        } else {
			$this->_controller = $this->getComponent('Request')->getParam('mod', 'index');
			$this->_action     = $this->getComponent('Request')->getParam('action', 'index');
        }
        if (!$this->_controller || !$this->_action) {
        	exit;
        }
        // 控制器和方法约定
        if (strtolower($this->_controller) === 'base') {
        	exit;
        }
        if (strtolower($this->_action) === '__construct') {
        	exit;
        }
        // 不允许访问下划线开头的方法
        if (trim($this->_action)[0] === '_') {
        	throw new CoreException('action not allow.');
        }
	}

	private function loadController($controller_name) 
	{
		if (isset($this->_loaded_controller[$controller_name])) {
			return $this->_loaded_controller[$controller_name];
		}

		//app私有 controller
		$controller_class_name = ucfirst($controller_name) . 'Controller';

		$controller_path       = $this->getControllerPath();
		$controller_class_file = $controller_path.DIRECTORY_SEPARATOR.$controller_class_name.'.php';

		if(is_file($controller_class_file)){

			include($controller_class_file);
			
			if(class_exists('Sub' . $controller_class_name,false)){
				$controller_class_name = 'Sub' . $controller_class_name;
			}
			$this->_loaded_controller[$controller_name] = new $controller_class_name();
			return $this->_loaded_controller[$controller_name];
		}

		//共用controller
		$controller_class_name = ucfirst($controller_name) . 'Controller';
		
		$controller_path       = $this->getDefaultControllerPath();
		$controller_class_file = $controller_path.DIRECTORY_SEPARATOR.$controller_class_name.'.php';

		if(is_file($controller_class_file)){
			if(!class_exists($controller_class_name,false)){
				include($controller_class_file);
			}
			$this->_loaded_controller[$controller_name] = new $controller_class_name();
			return $this->_loaded_controller[$controller_name];
		}

		return null;
	}

	public function setTimeZone()
	{
		date_default_timezone_set($this->_time_zone);
	}


	public function setComponents()
	{
		foreach($this->_components as $id=>$component)
		{
			$id = strtolower($id);
			$this->_componentConfig[$id]=$component;
		}
	}

	public function setComponent($id, $component)
	{
		$id = strtolower($id);
		if($component===null)
			unset($this->_created_components[$id]);
		else
		{
			$this->_created_components[$id] = $component;
			if(!$component->getIsInitialized())
				$component->init();
		}
	}

	public function getComponent($id,$createIfNull=true)
	{
		$id = strtolower($id);
		if(isset($this->_created_components[$id])){
			return $this->_created_components[$id];
		}
		else if(isset($this->_componentConfig[$id]) && $createIfNull)
		{
			$config=$this->_componentConfig[$id];
			if(!isset($config['enabled']) || $config['enabled'])
			{
				unset($config['enabled']);
				$component=$this->createComponent($config);
				$component->init();
				return $this->_created_components[$id]=$component;
			}
		}
	}	

	public function createComponent(array $config)
	{
		if(isset($config['class']))
		{
			$type=$config['class'];
			unset($config['class']);
		}
		else
			throw new CoreException('Object configuration must be an array containing a "class" element.');

		$object = new $type;

		foreach($config as $key=>$value){
			$object->$key = $value;
		}

		return $object;
	}

	public function getBasePath()
	{
		return APP_ROOT;
	}

	public function getControllerPath()
	{
		if($this->_controller_path!==null)
			return $this->_controller_path;
		else
			return $this->_controller_path = $this->getBasePath().DIRECTORY_SEPARATOR.'controllers';
	}

	public function getDefaultControllerPath()
	{

		return $this->_controller_path = $this->getBasePath().DIRECTORY_SEPARATOR.'controllers';
	}	

	public function getTemplatePath()
	{
		if($this->_view_path!==null)
			return $this->_view_path;
		else
			return $this->_view_path = $this->getBasePath().DIRECTORY_SEPARATOR.'template';
	}

	public function getDefaultTemplatePath()
	{

		return $this->_view_path = $this->getBasePath().DIRECTORY_SEPARATOR.'template';
	}

	public function getController()
	{
		return $this->_controller;
	}

	public function getAction()
	{
		return $this->_action;
	}

	public function getProperty($key)
	{
		$key = '_'.$key;
		return $this->$key;
	}

}
