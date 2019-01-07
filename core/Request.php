<?php

/*
* @author: 4061470@qq.com
*/

class Request extends Component
{
	private $_requestUri         = null;
	
	public $enableCsrfValidation = false;
	public $csrfTokenName        = 'NICE_CSRF_TOKEN';

	public function __construct($config=null)
	{
		$this->init();
	}
	
	public function init()
	{
		parent::init();
		if($this->enableCsrfValidation){
			Nice::event()->bindEventHandler('onBeginRequest', array($this,'validateCsrfToken'), Nice::app());
		}
	}

	public function getParam($name, $defaultValue=null)
	{
		return isset($_GET[$name]) ? $_GET[$name] : (isset($_POST[$name]) ? $_POST[$name] : $defaultValue);
	}

	public function isPostRequest()
	{
		return isset($_SERVER['REQUEST_METHOD']) && !strcasecmp($_SERVER['REQUEST_METHOD'],'POST');
	}	

	public function getIsAjaxRequest()
	{
		return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']==='XMLHttpRequest';
	}

	public function getUrl()
	{
		if($this->_requestUri===null)
		{
			if(isset($_SERVER['HTTP_X_REWRITE_URL'])) // IIS
				$this->_requestUri=$_SERVER['HTTP_X_REWRITE_URL'];
			else if(isset($_SERVER['REQUEST_URI']))
			{
				$this->_requestUri=$_SERVER['REQUEST_URI'];
				if(!empty($_SERVER['HTTP_HOST']))
				{
					if(strpos($this->_requestUri,$_SERVER['HTTP_HOST'])!==false)
						$this->_requestUri=preg_replace('/^\w+:\/\/[^\/]+/','',$this->_requestUri);
				}
				else
					$this->_requestUri=preg_replace('/^(http|https):\/\/[^\/]+/i','',$this->_requestUri);
			}
			else if(isset($_SERVER['ORIG_PATH_INFO']))  // IIS 5.0 CGI
			{
				$this->_requestUri=$_SERVER['ORIG_PATH_INFO'];
				if(!empty($_SERVER['QUERY_STRING']))
					$this->_requestUri.='?'.$_SERVER['QUERY_STRING'];
			}
			else
				throw new CoreException('CHttpRequest is unable to determine the request URI.');
		}

		return $this->_requestUri;
	}

	public function getUrlReferrer()
	{
		return isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:null;
	}	

	public function getUserAgent()
	{
		return isset($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:null;
	}

	public function getServerName()
	{
		return $_SERVER['SERVER_NAME'];
	}

	public function getServerPort()
	{
		return $_SERVER['SERVER_PORT'];
	}


	public function redirect($url, $statusCode=302)
	{
		header('Location: '.$url, true, $statusCode);
	}



	public function getCsrfToken()
	{
		if($this->_csrfToken===null)
		{
			$cookie = $_COOKIE[$this->csrfTokenName];
			if(($this->_csrfToken=$cookie)==null)
			{
				$this->_csrfToken = $this->createCsrfCookie();
			}
		}

		return $this->_csrfToken;
	}

	protected function createCsrfCookie()
	{
		$token                         = sha1(uniqid(mt_rand(),true));
		$_COOKIE[$this->csrfTokenName] = $token;
		return $token;
	}

	public function validateCsrfToken()
	{
		if($this->isPostRequest())
		{
			// only validate POST requests
			if(isset($_COOKIE[$this->csrfTokenName]) && isset($_POST[$this->csrfTokenName])){
				$tokenFromCookie = $_COOKIE[$this->csrfTokenName];
				$tokenFromPost   = $_POST[$this->csrfTokenName];
				$valid 			 = $tokenFromCookie===$tokenFromPost;
			}else{
				$valid = false;
			}
			if(!$valid)
				throw new BizException('The CSRF token could not be verified.');
		}
	}	


}
