<?php

/*
* @author: 4061470@qq.com
*/

class Component
{
	private $_inited = false;

	public function init()
	{
		$this->_inited = true;
	}

	public function getIsInitialized()
	{
		return $this->_inited;
	}

}
