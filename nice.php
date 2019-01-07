<?php

/*
* @author: 4061470@qq.com
*/

defined('NICE_DEBUG') or define('NICE_DEBUG',false);
defined('NICE_BEGIN_TIME') or define('NICE_BEGIN_TIME',microtime(true));
defined('NICE_PATH') or define('NICE_PATH',dirname(__FILE__));

class Nice
{
	private static $_app           = null;
	private static $_logger		   = null;
	private static $_imports 	   = array();
	private static $_includePaths  = array();
	private static $_coreClasses   = array(
		'Application'        => '/core/Application.php',
		'ConsoleApplication' => '/core/ConsoleApplication.php',
		'Component'          => '/core/Component.php',
		'CoreException'      => '/core/CoreException.php',
		'DbException'        => '/core/CoreException.php',
		'BizException'        => '/core/CoreException.php',
		'ObjectCreater'      => '/core/ObjectCreater.php',
		'Request'            => '/core/Request.php',
		'Event'              => '/core/Event.php',
		'Log'                => '/core/Log.php',
		'Validator'          => '/core/Validator.php',
		'DataBase'           => '/db/DataBase.php',
		'Memory'             => '/db/Memory.php',
	);

	public static function app()
	{
		return self::$_app;
	}
	
	//
	public static function initApplication($config=null)
	{
		if(self::$_app===null){
			self::$_app = new Application($config);
		}
		return self::$_app;
	}

	public static function initConsoleApplication($config=null)
	{
		if(self::$_app===null){
			self::$_app = new ConsoleApplication($config);
		}
		return self::$_app;		
	}

	public static function autoload($className)
	{
		//框架代码
		if(isset(self::$_coreClasses[$className])){
			include(NICE_PATH.self::$_coreClasses[$className]);
			return true;
		}else{
		//业务代码
			$class    = strtolower($className);
			if(strpos($class, 'logic')!==false){
				include(BASE_ROOT.'/source/logic/'.$className.'.php');
			}else if(strpos($class, 'dao')!==false){
				include(BASE_ROOT.'/source/dao/'.$className.'.php');
			}else if(strpos($class, 'controller')!==false){
				include(APP_ROOT.'/controllers/'.$className.'.php');
			}else if(strpos($class, 'helper')!==false){
				include(BASE_ROOT.'/source/helper/'.$className.'.php');
			}else if(strpos($class, 'widget')!==false){
				include(BASE_ROOT.'/source/widget/'.$className.'.php');
			}

			return class_exists($className,false) || interface_exists($className,false);
		}
		return false;
	}


	public static function import($path) 
	{
		if(!isset(self::$_imports[$path])) {
			$filepath = BASE_ROOT.'/source/'.$path.'.php';
			if(is_file($filepath)) {
				self::$_imports[$path] = true;
				return include($filepath);
			} else {
				throw new Exception('import file miss: '.$filepath);
			}
		}
		return true;
	}

	public static function event()
	{
		return ObjectCreater::create('Event');
	}

	public static function log($msg, $level=Log::LEVEL_ERROR)
	{
		if(self::$_logger===null){
			self::$_logger = Nice::app()->getComponent('Log');
		}
		self::$_logger->log($msg,$level);
	}

	public static function handleException($exception) 
	{
		HelperError::exception_error($exception);
	}


	public static function handleError($errno, $errstr, $errfile, $errline) 
	{
		if($errno) {
			HelperError::system_error($errstr, false, true, false);
		}
	}

	public static function handleShutdown() 
	{
		if(($error = error_get_last()) && $error['type']) {
			HelperError::system_error($error['message'], false, true, false);
		}
	}
}

spl_autoload_register(array('Nice','autoload'));
set_exception_handler(array('Nice', 'handleException'));
set_error_handler(array('Nice', 'handleError'));

if(defined('DEBUG_MOD') && true===DEBUG_MOD){
	//register_shutdown_function(array('Nice', 'handleShutdown'));
}
