<?php
class Memory
{
	public  $config;
	private $extension = array();
	private $memory;
	private $prefix;
	private $userprefix;
	public  $type;
	public  $enable = false;
	public  $debug  = array();

	public function __construct() 
	{
		$this->enable = extension_loaded('redis');
	}

	public function init() 
	{
		if($this->enable && is_array($this->config)) {
			$driver = $this->driver;
			$driver_path = dirname(__FILE__) . '/driver/'.$driver.'.php';
			if(is_file($driver_path)){
				require($driver_path);
			}
			$this->memory = new $driver();
			$this->memory->init($this->config);
			if(!$this->memory->enable) {
				$this->memory = null;
			}

			$this->prefix = isset($this->config['prefix']) ? $this->config['prefix'] : '';
		}
		if(is_object($this->memory)) {
			$this->enable = true;
			$this->type   = 'redis';	// str_replace('memory_driver_', '', get_class($this->memory));
		}
	}

	public function cmd($cmd, $key='', $value='', $ttl=0, $prefix='') 
	{
		if($cmd == 'check') {
			static $tttype	= null;
			if($tttype===null){
				$tttype = $this->enable ? $this->type : '';
			}
			return $tttype;
		} elseif($this->enable && in_array($cmd, array('set', 'get', 'rm', 'inc', 'dec', 'lpush', 'rpop', 'lget'))) {
			switch ($cmd) {
				case 'set':   return $this->set($key, $value, $ttl, $prefix); break;
				case 'get':   return $this->get($key, $value); break;
				case 'rm':    return $this->rm($key, $value); break;
				case 'inc':   return $this->inc($key, $value ? $value : 1); break;
				case 'dec':   return $this->dec($key, $value ? $value : 1); break;
	            case 'lpush': return $this->lpush($key, $value); break;
	            case 'rpop':  return $this->rpop($key, $value); break;
	            case 'lget':  return $this->lget($key,$value); break;
			}
		}
		return false;
	}

	public function get($key, $prefix = '') 
	{
		static $getmulti = null;
		$ret = false;
		if($this->enable) {
			if(!isset($getmulti)) $getmulti = method_exists($this->memory, 'getMulti');
			$this->userprefix = $prefix;
			if(is_array($key)) {
				if($getmulti) {
					$ret = $this->memory->getMulti($this->_key($key));
					if($ret !== false && !empty($ret)) {
						$_ret = array();
						foreach((array)$ret as $_key => $value) {
							$_ret[$this->_trim_key($_key)] = $value;
						}
						$ret = $_ret;
					}
				} else {
					$ret = array();
					$_ret = false;
					foreach($key as $id) {
						if(($_ret = $this->memory->get($this->_key($id))) !== false && isset($_ret)) {
							$ret[$id] = $_ret;
						}
					}
				}
				if(empty($ret)) $ret = false;
			} else {
				$ret = $this->memory->get($this->_key($key));
				if(!isset($ret)) $ret = false;
			}
		}
		return $ret;
	}

	public function set($key, $value, $ttl = 0, $prefix = '') 
	{

		$ret = false;
		if($value === false) $value = '';
		if($this->enable) {
			$this->userprefix = $prefix;
			$ret = $this->memory->set($this->_key($key), $value, $ttl);
		}
		return $ret;
	}

	public function rm($key, $prefix = '') 
	{
		$ret = false;
		if($this->enable) {
			$this->userprefix = $prefix;
			$key = $this->_key($key);
			foreach((array)$key as $id) {
				$ret = $this->memory->rm($id);
			}
		}
		return $ret;
	}

	public function clear() 
	{
		$ret = false;
		if($this->enable && method_exists($this->memory, 'clear')) {
			$ret = $this->memory->clear();
		}
		return $ret;
	}

	public function inc($key, $step = 1) 
	{
		static $hasinc = null;
		$ret = false;
		if($this->enable) {
			if(!isset($hasinc)) $hasinc = method_exists($this->memory, 'inc');
			if($hasinc) {
                                $this->userprefix = '';
				$ret = $this->memory->inc($this->_key($key), $step);
			} else {
				if(($data = $this->memory->get($key)) !== false) {
					$ret = ($this->memory->set($key, $data + ($step)) !== false ? $this->memory->get($key) : false);
				}
			}
		}
		return $ret;
	}

	public function dec($key, $step = 1) 
	{
		static $hasdec = null;
		$ret = false;
		if($this->enable) {
			if(!isset($hasdec)) $hasdec = method_exists($this->memory, 'dec');
			if($hasdec) {
				$ret = $this->memory->dec($this->_key($key), $step);
			} else {
				if(($data = $this->memory->get($key)) !== false) {
					$ret = ($this->memory->set($key, $data - ($step)) !== false ? $this->memory->get($key) : false);
				}
			}
		}
		return $ret;
	}

	private function _key($str) 
	{
		$perfix = $this->prefix.$this->userprefix;
		if(is_array($str)) {
			foreach($str as &$val) {
				$val = $perfix.$val;
			}
		} else {
			$str = $perfix.$str;
		}
		return $str;
	}

	private function _trim_key($str) 
	{
		return substr($str, strlen($this->prefix.$this->userprefix));
	}

	public function getextension() 
	{
		return $this->extension;
	}

	public function getconfig() {
		return $this->config;
	}
        
    public function lpush($key, $value)
    {
        $ret = false;
		if($value === false) $value = '';
		if($this->enable) {
			$this->userprefix = '';
			$ret = $this->memory->LPUSH($this->_key($key),$value);
		}
		return $ret;
    }
    
    public function rpop($key)
    {
        $ret = false;
		if($this->enable) {
			$this->userprefix = '';
			$ret = $this->memory->RPOP($this->_key($key));
		}
		return $ret;
    }
    
    public function lget($key, $index)
    {
    	$ret = false;
		if($index === false) $index = '';
		if($this->enable) {
			$this->userprefix = '';
			$ret = $this->memory->LGET($this->_key($key), $index);
		}
		return $ret;
    }

    public function close()
    {
    	return $this->memory->close();
    }

	public function get_memory_obj()
	{
		return $this->memory;
	}

}
