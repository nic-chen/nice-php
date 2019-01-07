<?php
class memory_driver_redis_cluster
{
	public $enable = false;
	public $obj;
	
	private $conf;	// 配置信息存放
	private $_conn;	//redis连接
	
	// 初使化
	public function init($config) 
	{
		if(!empty($config['servers'])) {
			$this->conf   = $config;
			$this->enable = true;
		}
	}


	// 主 redis 连接
	private function _connect()
	{
		if($this->_conn){
			return $this->_conn;
		}
		
		$connect_timeout = isset($this->conf['connect_timeout']) ? $this->conf['connect_timeout'] : 0;
		$read_timeout    = isset($this->conf['read_timeout']) ? $this->conf['read_timeout'] : 0;
		$pconnect        = isset($this->conf['pconnect']) && $this->conf['pconnect']  ? true : false;

    	$this->_conn = new RedisCluster(NULL, $this->conf['servers'],  $connect_timeout,  $read_timeout, $pconnect);   //

        return $this->_conn;
	}

	/**
	 * 判断主是否连接上 	(如果一次都没有连接过就连接一次)
	 * 
	 * 返回 	true or false
	 * **/
	private function master()
	{
		if($this->_conn){
			return $this->_conn;
		}
		return $this->_connect();
	}

		
	/**
	 * 判断主是否连接上 	(如果一次都没有连接过就连接一次)
	 * 
	 * 返回 	true or false
	 * **/
	private function slave()
	{
		if($this->_conn){
			return $this->_conn;
		}
		return $this->_connect();
	}	
	
	// 获得key
	public function get($key) 
	{
		if(is_array($key)) {
			return $this->getMulti($key);
		}
		
		if( !$this->slave()){return false;}
		$data = $this->slave()->get($key);
		if( !empty($data)){
			$data	= @ json_decode($data , true);
		}elseif($data==='0' || $data===0){
			//什么都不用做
		}else{
			$data	= false;
		}
		return $data;
	}
	
	// 获取
	public function getMulti($keys) 
	{
		if( !$this->slave()){return false;}
		
		$result = $this->slave()->mGet($keys);
		$newresult = array();
		$index = 0;
		foreach($keys as $key) {
			if($result[$index] !== false) {
				$newresult[$key] = @ json_decode($result[$index], true);
			}
			$index++;
		}
		unset($result);
		return $newresult;
	}
	
	
	// 写
	public function set($key, $value, $ttl = 0) 
	{
		if( !$this->master()){return false;}
		$value 	= @json_encode($value);	// 转成  json格式
		//echo $key.'-----------'.$value."\r\n\r\n\r\n\r\n\r\n\r\n";
		if($ttl==0){
			$ttl 	= 3605;
		}
		if($ttl) {
			return $this->master()->setex($key, $ttl, $value);
		} else {
			return $this->master()->set($key, $value);
		}
	}
	
	// 删
	public function rm($key) 
	{
		if( !$this->master()){return false;}
		return $this->master()->del($key);
	}
	
	// 写
	public function setMulti($arr, $ttl=0) 
	{
		if(!is_array($arr)) {
			return FALSE;
		}
		foreach($arr as $key => $v) {
			$this->set($key, $v, $ttl);
		}
		return TRUE;
	}
	
	// 自增
	public function inc($key, $step = 1) 
	{
		if( !$this->master()){return false;}
		return $this->master()->incr($key, $step);
	}
	
	// 自减
	public function dec($key, $step = 1) 
	{
		if(!$this->master()){return false;}
		return $this->master()->decr($key, $step);
	}

	// 写
	function getSet($key, $value) {
		if( !$this->master()){return false;}
		$value 	=  @json_encode($value);	// 转成  json格式
		return $this->master()->getSet($key, $value);
	}
	
	// 加元素
	function sAdd($key, $value) {
		if( !$this->master()){return false;}
		$value 	= @json_encode($value);
		return $this->master()->sAdd($key, $value);
	}

	function sRemove($key, $value) {
		if( !$this->master()){return false;}
		return $this->master()->sRemove($key, $value);
	}

	function sMembers($key) {
		if( !$this->slave()){return false;}
		return $this->slave()->sMembers($key);
	}

	function sIsMember($key, $member) {
		if( !$this->slave()){return false;}
		return $this->slave()->sismember($key, $member);
	}

	 //直接调用redis扩展的函数
     public function __call($name, $arguments) 
     {
		if (!$this->master()) {
			return false;
		}

		if(!method_exists($this->_conn, $name)){
			return false;
		}

		return call_user_func_array(array($this->_conn, $name), $arguments);
     }

	public function close() 
	{
		$res = $this->_conn && $this->_conn->close();

		return $res;
	}

	public function __destruct()
	{
		$this->close();
	}



}
