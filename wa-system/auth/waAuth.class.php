<?php 

/*
 * This file is part of Webasyst framework.
 *
 * Licensed under the terms of the GNU Lesser General Public License (LGPL).
 * http://www.webasyst.com/framework/license/
 *
 * @link http://www.webasyst.com/
 * @author Webasyst LLC
 * @copyright 2011 Webasyst LLC
 * @package wa-system
 * @subpackage auth
 */
class waAuth implements waiAuth
{	
	protected $options = array(
		'cookie_expire' => 2592000,
	    'is_user' => true, // only contacts with is_user = 1 can auth
	    'login' => 'login'
	);
	
	public function __construct($options = array())
	{
		if (is_array($options)) {
			foreach ($options as $k => $v) {
				$this->options[$k] = $v;
			}
		}
	}	
	
	/**
	 * Auth user returns result of auth
	 * 
	 * @param waAuthAdapter $auth_adapter
	 * @return mixed
	 */
	public function auth($params = array())
	{
		$result = $this->_auth($params);
		if ($result !== false) {
			waSystem::getInstance()->getStorage()->write('auth_user', $result);
			waSystem::getInstance()->getUser()->init();
		}
		return $result;
	}
	
	public function isAuth()
	{
		return waSystem::getInstance()->getStorage()->read('auth_user');
	}
	
	
	protected function getByLogin($login)
	{
        $result = array();
	    if ($this->options['login'] == 'login') { 
    	    $model = new waContactModel();
	    	$result = $model->getByField('login', $login);
	    } elseif ($this->options['login'] == 'email') {
	        $email_model = new waContactEmailsModel();
	        $row = $email_model->getByField(array('email' => $login, 'sort' => 0));
	        if ($row) {
	            $model = new waContactModel();
	            $result = $model->getById($row['contact_id']);
	        }
	    }
        if ($result) {
            $this->checkBan($result);
        }
        return $result;
	}

    protected function checkBan($data)
    {
        $contact_data_model = new waContactDataModel();
        $rows = $contact_data_model->getByField(array('contact_id' => $data['id'],
            'field' => array('banned_datetime', 'banned_reason', 'sort' => 0)), true);
        $result = array();
        foreach ($rows as $row) {
            $result[$row['field']] = $row['value'];
        }
        if (isset($result['banned_datetime']) && $result['banned_datetime']) {
            throw new waException(isset($result['banned_reason']) ? $result['banned_reason'] : 'You are banned!');
        }
    }
	
	protected function _auth($params)
	{	
	    if ($params && isset($params['id'])) {
	        $contact_model = new waContactModel();
	        $user_info = $contact_model->getById($params['id']);
	        if ($user_info && ($user_info['is_user'] || !$this->options['is_user'])) {
	            waSystem::getInstance()->getResponse()->setCookie('auth_token', null, -1);
				return array(
					'id' => $user_info['id'], 
					'login' => $user_info['login'],
					'is_user' => $user_info['is_user']
				);
	        }
	        return false;
	    } elseif ($params && isset($params['login']) && isset($params['password'])) {
			$login = $params['login'];
			$password = $params['password'];
		} elseif (waRequest::getMethod() == 'post' && waRequest::post('wa_auth_login')) {
			$login = waRequest::post('login');
			$password = waRequest::post('password');
			if (!strlen($login)) {
				throw new waException(_ws('Login is required'));
			}
		} else {
			$login = null;
		}
		if ($login && strlen($login)) {
            $user_info = $this->getByLogin($login);
			if ($user_info && ($user_info['is_user'] || !$this->options['is_user']) &&
				waSystem::getInstance()->getUser()->getPasswordHash($password) ===	$user_info['password']) {
				$response = waSystem::getInstance()->getResponse();
				// if remember
				if (waRequest::post('remember')) {
                    $response->setCookie('auth_token', $this->getToken($user_info), time() + 2592000);
                    $response->setCookie('remember', 1);
				} else {
					$response->setCookie('remember', null, -1);
				}	
				
				// return array with compact user info 
				return array(
					'id' => $user_info['id'], 
					'login' => $user_info['login'],
					'is_user' => $user_info['is_user']
				);
			} else {
				throw new waException(_ws('Invalid login or password'));	
			}
		} elseif ($token = waRequest::cookie('auth_token')) {
		    try {
		        $r = waSystem::getSetting('rememberme', 1, 'webasyst');
		    } catch (waDbException $e) {
		        if ($e->getCode() == 1146) {
		            return false;
		        }
		    }
		    $model = new waContactModel();
			$response = waSystem::getInstance()->getResponse();
			$id = substr($token, 15, -15);
			$user_info = $model->getById($id);
            $this->checkBan($user_info);
			if ($user_info && ($user_info['is_user'] || !$this->options['is_user']) && 
				$token === $this->getToken($user_info)) {
				$response->setCookie('auth_token', $token, time() + 2592000);
				return array(
					'id' => $user_info['id'], 
					'login' => $user_info['login'],
				    'is_user' => $user_info['is_user']
				);
			} else {
				$response->setCookie('auth_token', null, -1);
			}
		}
		return false;
	}	
	
	public function getToken($user_info)
	{
		$hash = md5($user_info['login'] . $user_info['password']);
		return substr($hash, 0, 15).$user_info['id'].substr($hash, -15);
	}	
	
	/**
	 * Clear all auth tokens in storage and cookies
	 * 
	 * @return void
	 */
	public function clearAuth()
	{   
		waSystem::getInstance()->getStorage()->destroy();
		if (waRequest::cookie('auth_token')) {
			waSystem::getInstance()->getResponse()->setCookie('auth_token', null, -1);
		}
	}
	
	public function getOptions()
	{
		return $this->options;	
	}
	
	public function getOption($name)
	{
	    return $this->options[$name];
	}
}
