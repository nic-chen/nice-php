<?php

/*
* @author: 4061470@qq.com
*/

class CoreException extends Exception
{
}

class DbException  extends Exception
{
	public $sql;

	public function __construct($message, $code=0, $sql='') 
	{
		$this->sql = $sql;
		parent::__construct($message, $code);
	}

	public function getSql() 
	{
		return $this->sql;
	}
}

class BizException  extends Exception
{
	public $extra = null;
	public static $output_type = null;
	public static $render_file = '';

	public function __construct($message, $code=0, $extra=null) 
	{
		$this->extra = $extra;
		parent::__construct($message, $code);
	}

	public function getExtra() 
	{
		return $this->extra;
	}

	public static function set_output_type($output_type, $render_file = '')
	{
		self::$output_type = $output_type;
		if ($render_file) {
			self::$render_file = $render_file;
		}
	}

	//抛出异常
	public static function throw_exception($throw, $data, $extra=array())
	{
		if($throw){
			throw new BizException($data['message'], $data['code'], $extra);
		}
	}

	//处理异常
	public static function handle_exception($e, $controller)
	{
		$data = array(
			'code'    => $e->getCode(),
			'message' => $e->getMessage(),
		);
		$extra = $e->getExtra();

		//执行下 after action
		$controller->after_action($controller, $data);

		$output_type = isset($extra['output_type']) && $extra['output_type'] ? $extra['output_type'] : (self::$output_type ? self::$output_type : null);

		//错误页
		if($output_type=='error_page'){
			self::show_error_page($controller, array_merge($data, $extra));
		//指定页面输出
		} else if($output_type == 'render_file') {
			$controller->render(self::$render_file, array(
				'data_code' => $data['code'],
				'data_message' => $data['message'],
				));
		//跳转
		}else if($output_type=='location' && isset($extra['url']) && $extra['url']){
			self::location($extra['url']);
		//json
		}else{
			$filter   = false;
			$callback = isset($extra['callback']) && $extra['callback'] ? $extra['callback'] : null;
			$controller->render_json($data, $filter, $callback);
		}
	}

	public static function show_error_page($controller, $data)
	{
		// $controller->render_file(BASE_ROOT.'/pc/template/common/error_page.php', $data);
		header('Location: '.DOMAIN.'html/error/not_found.html');
		exit;
	}

	public function location($url)
	{
		if(defined('IN_MOBILE') && strpos($url, 'mobile') === false) {
			if (strpos($url, '?') === false) {
				$url = $url.'?mobile=yes';
			} else {
				if(strpos($url, '#') === false) {
					$url = $url.'&mobile=yes';
				} else {
					$str_arr    = explode('#', $url);
					$str_arr[0] = $str_arr[0].'&mobile=yes';
					$url        = implode('#', $str_arr);
				}
			}
		}

		header('Location: '.$url);
		exit();
	}



}