<?php

class memory_driver_redis{
	var $enable;
	var $obj;
	
	private $conf;	// 配置信息存放
	private $master_conn;	// 主redis连接
	private $slave_conn;	// 从redis连接
	private $slave_conf;	// 当前连接的 从 redis
	
	
	
	// 初使化
	public function init($config) 
	{
		if(is_array($config) && isset($config['redis']) && $config['redis']) {
			$this->conf	= $config['redis'];
			/***  配置纠正  填充默认值   **/
			if( (!isset($this->conf['outtime'])) || floatval($this->conf['outtime'])<0.1 ){
				$this->conf['outtime'] 	= '0.5';
			}
			if( !isset($this->conf['selectdb']) ){
				$this->conf['selectdb'] 	= '0';
			}
			
			if(isset($this->conf['slave'])){
				foreach($this->conf['slave'] as $key=>$arr ){
					if( (!isset($this->conf['slave'][$key]['outtime'])) || floatval($this->conf['slave'][$key]['outtime'])<0.1 ){
						$this->conf['slave'][$key]['outtime'] 	= '0.5';
					}
					if( !isset($this->conf['slave'][$key]['selectdb']) ){
						$this->conf['slave'][$key]['selectdb'] 	= '0';
					}
				}
			}
			$this->enable	= true;
		}
	}


	
	/**
	 * 判断主是否连接上 	(如果一次都没有连接过就连接一次)
	 * 
	 * 返回 	true or false
	 * **/
	private function master(){
		if(!$this->master_conn){
			$this->master_connect();
		}
		if( !is_object($this->master_conn) ){
			return false;
		}else{
			return true;
		}
	}
	
	// 主 redis 连接
	private function master_connect(){
		if($this->master_conn){
			return $this->master_conn;
		}
		
		$this->master_conn = new Redis();
        try {
		    $connect	= $this->master_conn->pconnect($this->conf['server'],$this->conf['port'],$this->conf['timeout']);
			if(!$connect){
				$this->master_conn = false;
				HelperLog::writelog('redis', date('Y-m-d H:i:s ').' 没连上：'.$this->conf['server'].' '.$this->conf['port'].' '.$this->conf['timeout']);
			}elseif( isset($this->conf['pwd']) && $this->conf['pwd']!=''){
			    $this->master_conn->auth($this->conf['pwd']);	// 密码
			}
			
			$this->master_conn->select($this->conf['selectdb']);

		} catch (Exception $e) {
			$this->master_conn = false;
			HelperLog::writelog('redis', date('Y-m-d H:i:s ').$e->getMessage());
		}

        return $this->master_conn;
		
	}
	
	
	
	
	/**
	 * 判断主是否连接上 	(如果一次都没有连接过就连接一次)
	 * 
	 * 返回 	true or false
	 * **/
	private function slave(){
		if(!$this->slave_conn){
			$this->slave_connect();
		}
		if(!is_object($this->slave_conn)){
			return false;
		}else{
			return true;
		}
	}
	
	/**
	 * 从 redis 的连接
	 * ***/
	private function slave_connect(){
		if($this->slave_conn){
			return $this->slave_conn;
		}
		
		if( isset($this->conf['slave']) && is_array($this->conf['slave']) ){
			$key	= array_rand($this->conf['slave']);
			$this->slave_conf	= $this->conf['slave'][$key];
			
			$this->slave_conn	= new Redis();
			try{
				$connent	= $this->slave_conn->pconnect($this->slave_conf['server'],$this->slave_conf['port'],$this->slave_conf['timeout']); 
				if(!$connent){
					$this->slave_conn = false;
					HelperLog::writelog('redis', date('Y-m-d H:i:s ').' 没连上：'.$this->conf['server'].' '.$this->conf['port'].' '.$this->conf['timeout']);
				}
				if( isset($this->slave_conf['pwd']) && $this->slave_conf['pwd']!=''){
				    $this->slave_conn->auth($this->slave_conf['pwd']);	// 密码
				}
				
				$this->master_conn->select($this->conf['selectdb']);

			}catch(Exception $e){
				$this->slave_conn = false;
				HelperLog::writelog('redis', date('Y-m-d H:i:s ').$e->getMessage());
			}

			
		}else{	// 没有从库  用主
			if(!$this->master_conn){	// 主没有连接
				$this->master_connect();
			}
			$this->slave_conn = $this->master_conn;
		}
		
		return $this->slave_conn;
	}
	

	
	
	// 获得key
	function get($key) {
		if(is_array($key)) {
			return $this->getMulti($key);
		}
		
		if( !$this->slave()){return false;}
		$data = $this->slave_conn->get($key);
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
	function getMulti($keys) {
		if( !$this->slave()){return false;}
		
		$result = $this->slave_conn->getMultiple($keys);
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
	
	// 选择
	function select($db=0) {
		if( $this->master() ){
			$this->master_conn->select($db);
		}
		if( $this->slave() ){
			$this->slave_conn->select($db);
		}
		return true;
	}
	
	// 写
	function set($key, $value, $ttl = 0) {
		if( !$this->master()){return false;}
		$value 	= @json_encode($value);	// 转成  json格式
		//echo $key.'-----------'.$value."\r\n\r\n\r\n\r\n\r\n\r\n";
		if($ttl==0){
			$ttl 	= 3605;
		}
		if($ttl) {
			return $this->master_conn->setex($key, $ttl, $value);
		} else {
			return $this->master_conn->set($key, $value);
		}
	}
	
	// 删
	function rm($key) {
		if( !$this->master()){return false;}
		return $this->master_conn->delete($key);
	}
	
	// 写
	function setMulti($arr, $ttl=0) {
		if(!is_array($arr)) {
			return FALSE;
		}
		foreach($arr as $key => $v) {
			$this->set($key, $v, $ttl);
		}
		return TRUE;
	}
	
	// 自增
	function inc($key, $step = 1) {
		if( !$this->master()){return false;}
		return $this->master_conn->incr($key, $step);
	}
	
	// 自减
	function dec($key, $step = 1) {
		if( !$this->master()){return false;}
		return $this->master_conn->decr($key, $step);
	}
	
	// 写
	function getSet($key, $value) {
		if( !$this->master()){return false;}
		$value 	=  @json_encode($value);	// 转成  json格式
		return $this->master_conn->getSet($key, $value);
	}
	
	function sRemove($key, $value) {
		if( !$this->master()){return false;}
		return $this->master_conn->sRemove($key, $value);
	}

	function sMembers($key) {
		if( !$this->slave()){return false;}
		return $this->slave_conn->sMembers($key);
	}
	
	// 获得集合值
	function keys($key) {
		if( !$this->slave()){return false;}
		return $this->slave_conn->keys($key);
	}
	
	// 设置 key 生成时间
	function expire($key, $second){
		if( !$this->master()){return false;}
		return $this->master_conn->expire($key, $second);
	}

	function sCard($key) {
		if( !$this->slave()){return false;}
		return $this->slave_conn->sCard($key);
	}
	
	// 添加元素
	function hSet($key, $field, $value) {
		if( !$this->master()){return false;}
		return $this->master_conn->hSet($key, $field, $value);
	}

	public function hGet($key, $field) 
	{
		if( !$this->master()){return false;}
		return $this->master_conn->hGet($key, $field);
	}

	public function hMSet($key, $data)
	{
		if( !$this->master()){return false;}
		return $this->master_conn->hMSet($key, $data);
	}

	public function hmGet($key, $fields)
	{
		if( !$this->master()){return false;}
		return $this->master_conn->hmGet($key, $fields);
	}

	function hDel($key, $field) {
		if( !$this->master()){return false;}
		return $this->master_conn->hDel($key, $field);
	}

	function hLen($key) {
		if( !$this->slave()){return false;}
		return $this->slave_conn->hLen($key);
	}

	function hVals($key) {
		if( !$this->slave()){return false;}
		return $this->slave_conn->hVals($key);
	}
	
	// 加值
	function hIncrBy($key, $field, $incr){
		if( !$this->master()){return false;}
		return $this->master_conn->hIncrBy($key, $field, $incr);
	}
	
	// 获取
	function hGetAll($key) {
		if( !$this->slave()){return false;}
		return $this->slave_conn->hGetAll($key);
	}

	function sort($key, $opt) {
		if( !$this->master()){return false;}
		return $this->master_conn->sort($key, $opt);
	}

	function exists($key) {
		if( !$this->slave()){return false;}
		return $this->slave_conn->exists($key);
	}

	function clear() {
		if( $this->master()){
			$this->master_conn->flushAll();
		}
		if( $this->slave()){
			$this->slave_conn->flushAll();
		}
		return true;
	}
        
    public function LPUSH($key, $value){
            if( !$this->master()){return false;}
	return $this->master_conn->LPUSH($key,$value);
    }
    
    public function RPOP($key){
            if( !$this->master()){return false;}
	return $this->slave_conn->RPOP($key);
        
    }
    public function LGET($key,$index){
            if( !$this->slave()){return false;}
	return $this->slave_conn->lGet($key, $index);
        
    }
	public function lLen($key) {
		if (! $this->slave ()) {
			return false;
		}
		return $this->slave_conn->lLen ( $key );
	}
	public function lRange($key,$start,$stop){
		if (! $this->slave ()) {
			return false;
		}
		return $this->slave_conn->lrange($key,$start,$stop) ;
	}
	public function lTrim($key,$start,$stop){
		if (! $this->slave ()) {
			return false;
		}
		return $this->slave_conn->ltrim($key,$start,$stop) ;
	}

	 //直接调用redis扩展的函数
     public function __call($name, $arguments) 
     {
		if (!$this->slave()) {
			return false;
		}

		if(!method_exists($this->slave_conn, $name)){
			return false;
		}

		return call_user_func_array(array($this->slave_conn, $name), $arguments);
     }


	public function close() 
	{
		$res = $this->slave_conn && $this->slave_conn->close();
		$ret = $this->master_conn && $this->master_conn->close();

		return $res || $ret;
	}

	public function __destruct()
	{
		$this->close();
	}



}

?>