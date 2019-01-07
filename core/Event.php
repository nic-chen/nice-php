<?php

/*
* @author: 4061470@qq.com
*/

class Event
{
	private $_events = array();
	private $_handlers = array();

	public function hasEvent($name, $obj)
	{
		$obj = $obj ? $obj : $this;
		return !strncasecmp($name,'on',2) && method_exists($obj, $name);
	}

	public function hasEventHandler($name, $obj=null)
	{
		$name     = strtolower($name);
		$obj      = $obj ? $obj : $this;
		$obj_name = get_class($obj);
		return isset($this->_handlers[$obj_name]) && isset($this->_handlers[$obj_name][$name]) && count($this->_handlers[$obj_name][$name]);
	}

	public function getEventHandlers($name, $obj=null)
	{
		$name     = strtolower($name);
		$obj      = $obj ? $obj : $this;
		$obj_name = get_class($obj);

		if($this->hasEvent($name, $obj))
		{
			$this->_handlers[$obj_name]          = isset($this->_handlers[$obj_name]) ? $this->_handlers[$obj_name] : array();
			$this->_handlers[$obj_name][$name]   = isset($this->_handlers[$obj_name][$name]) ? $this->_handlers[$obj_name][$name] : array();
			return $this->_handlers[$obj_name][$name];
		}else{
			$message = sprintf('Event "%s.%s" is not defined.', $obj_name, $name);
			throw new CoreException($message);
		}
	}

	public function bindEventHandler($name, $handler, $obj=null)
	{
		$name                                = strtolower($name);
		$obj                                 = $obj ? $obj : $this;
		$obj_name                            = get_class($obj);
		$this->_handlers[$obj_name]          = isset($this->_handlers[$obj_name]) ? $this->_handlers[$obj_name] : array();
		$this->_handlers[$obj_name][$name]   = isset($this->_handlers[$obj_name][$name]) ? $this->_handlers[$obj_name][$name] : array();
		
		$this->_handlers[$obj_name][$name][] = $handler;
	}

	public function unbindEventHandler($name, $handler, $obj=null)
	{
		$name     = strtolower($name);
		$obj      = $obj ? $obj : $this;
		$obj_name = get_class($obj);

		if($this->hasEventHandler($name, $obj)){
			foreach($this->_handlers[$obj_name][$name] as $key=>$_handler){
				if($_handler==$handler){
					unset($this->_handlers[$obj_name][$name][$key]);
				}
			}
			//$this->getEventHandlers($name)->remove($handler)!==false;
		}	
		return false;
	}

	public function raiseEvent($name, $obj=null)
	{
		$name     = strtolower($name);
		$obj      = $obj ? $obj : $this;
		$obj_name = get_class($obj);

		if(isset($this->_handlers[$obj_name]) && isset($this->_handlers[$obj_name][$name])){
			foreach($this->_handlers[$obj_name][$name] as $handler)
			{
				if(is_string($handler)){
					call_user_func(array($obj, $handler));
				}else if(is_callable($handler, true)){
					if(is_array($handler))
					{
						// an array: 0 - object, 1 - method name
						list($object, $method) = $handler;
						if(is_string($object)){	// static method call
							call_user_func(array($obj, $handler));
						}else if(method_exists($object,$method)){
							$object->$method();
						}else{
							$message = sprintf('Event "{%s}.{%s}" is attached with an invalid handler "{%s}".', $obj_name, $name, $handler[1]);
							throw new CoreException($message);
						}
					}else{
						call_user_func(array($obj, $handler));
					}
					
				}
				else{
					$message = sprintf('Event "%s.%s" is attached with an invalid handler "%s".', $obj_name, $name, gettype($handler));
					throw new CoreException($message);
				}

			}
		}else if(!$this->hasEvent($name, $obj)){
			$message =  sprintf('Event "%s.%s" is not defined.', $obj_name, $name);
			throw new CoreException($message);
		}
	}

}
