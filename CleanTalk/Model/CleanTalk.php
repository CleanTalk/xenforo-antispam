<?php
class CleanTalk_Model_CleanTalk extends XFCP_CleanTalk_Model_CleanTalk {

    protected function _allowRegistration(array $user, Zend_Controller_Request_Http $request) {
	$decisions = parent::_allowRegistration($user, $request);
	if (!is_array($decisions)) {
	    $decisions = array(self::RESULT_ALLOWED);
	}
	if (!is_array($this->_resultDetails)) {
	    $this->_resultDetails = array();
	}
	$decisions[] = $this->_checkNewUser($user, $request);
        return $decisions;
    }

    protected function _checkNewUser(array $user, Zend_Controller_Request_Http $request) {
        $decision = self::RESULT_ALLOWED;

	$options = XenForo_Application::getOptions();
	if ($options->get('cleantalk', 'enabled')) {
	    if(!is_array($this->_resultDetails)) {
		$this->_resultDetails = array();
	    }

	    $spam_check = array();
	    $spam_check['type'] = 'register';
	    $spam_check['sender_email'] = $user['email'];
	    $spam_check['sender_nickname'] = $user['username'];
	    $spam_check['timezone'] = $user['timezone'];
	    
	    $field_name = CleanTalk_Base_CleanTalk::getCheckjsName();
	    
	   	if (!isset($_COOKIE[$field_name])) {
	   	    $checkjs = NULL;
	   	}
	   	//elseif ($_COOKIE[$field_name] == CleanTalk_Base_CleanTalk::getCheckjsValue()) {
	   	elseif (in_array($_COOKIE[$field_name], CleanTalk_Base_CleanTalk::getCheckJSArray())) {
	   	    $checkjs = 1;
	   	}
	   	else {
	   	    $checkjs = 0;
	   	}

	    $spam_result = $this->_checkSpam($spam_check, $options);
	    if (isset($spam_result)
		&& is_array($spam_result)
		&& $spam_result['errno'] == 0
		&& $spam_result['allow'] != 1 ||
		($spam_result['errno'] !=0 && $checkjs != 1)
	    ) {
		$decision = self::RESULT_DENIED;
		$this->_resultDetails[] = array(
        	    'phrase' => 'cleantalk_response',
        	    'data' => array('response' => $spam_result['ct_result_comment']
        	    )
		);
	    }

	}
        return $decision;
    }

    protected function _checkSpam($spam_check, $options) {
	require_once 'CleanTalk/Base/cleantalk.class.php';

	$ct_authkey = $options->get('cleantalk', 'apikey');

	$dataRegistryModel = $this->getModelFromCache('XenForo_Model_DataRegistry');
	$ct_ws = $dataRegistryModel->get('cleantalk_ws');
	if (!$ct_ws) {
	    $ct_ws = array(
		'work_url' => 'http://moderate.cleantalk.ru',
		'server_url' => 'http://moderate.cleantalk.ru',
		'server_ttl' => 0,
		'server_changed' => 0
	    );
	}

	$field_name = CleanTalk_Base_CleanTalk::getCheckjsName();
	if (!isset($_COOKIE[$field_name])) {
	    $checkjs = NULL;
	}
	//elseif ($_COOKIE[$field_name] == CleanTalk_Base_CleanTalk::getCheckjsValue()) {
	elseif (in_array($_COOKIE[$field_name], CleanTalk_Base_CleanTalk::getCheckJSArray())) {
	    $checkjs = 1;
	}
	else {
	    $checkjs = 0;
	}

	$user_agent = $_SERVER['HTTP_USER_AGENT'];
	$refferrer = $_SERVER['HTTP_REFERER'];

	$ct = new Cleantalk();
	$ct->work_url = $ct_ws['work_url'];
	$ct->server_url = $ct_ws['server_url'];
	$ct->server_ttl = $ct_ws['server_ttl'];
	$ct->server_changed = $ct_ws['server_changed'];
	
	$options = XenForo_Application::getOptions();
	$ct_options=array('enabled' => $options->get('cleantalk', 'enabled'),
		'apikey' => $options->get('cleantalk', 'apikey'));

	$sender_info = json_encode(
	    array(
    		'cms_lang' => 'en',
    		'REFFERRER' => $refferrer,
    		'post_url' => $refferrer,
    		'USER_AGENT' => $user_agent,
    		'ct_options' => json_encode($ct_options)
	    )
	);

	$ct_request = new CleantalkRequest();
	$ct_request->auth_key = $ct_authkey;
	$ct_request->agent = 'xenforo-15';
	$ct_request->response_lang = 'en';
	$ct_request->js_on = $checkjs;
	$ct_request->sender_info = $sender_info;
	$ct_request->sender_email = $spam_check['sender_email'];
	$ct_request->sender_nickname = $spam_check['sender_nickname'];
	$ct_request->sender_ip = $ct->ct_session_ip($_SERVER['REMOTE_ADDR']);

	$ct_submit_time = NULL;
//	session_start();
	switch ($spam_check['type']) {
		case 'comment':
		      $stored_time = XenForo_Application::getSession()->get('ct_submit_comment_time');
		      if (isset($stored_time)) {
		        $ct_submit_time = time() - $stored_time;
		      }
		      $timelabels_key = 'e_comm';

		      $ct_request->submit_time = $ct_submit_time;
		      $ct_request->message = $spam_check['message_title'] . " \n\n" . $spam_check['message_body'];

		      $example = '';
		      $a_example = array();
		      $a_example['title'] = $spam_check['example_title'];
		      $a_example['body'] = $spam_check['example_body'];
		      $a_example['comments'] = $spam_check['example_comments'];

		      // Additional info.
		      $post_info = '';
		      $a_post_info['comment_type'] = 'comment';

		      // JSON format.
		      $example = json_encode($a_example);
		      $post_info = json_encode($a_post_info);

		      // Plain text format.
		      if ($example === FALSE) {
		        $example = '';
		        $example .= $a_example['title'] . " \n\n";
		        $example .= $a_example['body'] . " \n\n";
		        $example .= $a_example['comments'];
		      }
		      if ($post_info === FALSE) {
		        $post_info = '';
		      }

		      // Example text + last N comments in json or plain text format.
		      $ct_request->example = $example;
		      $ct_request->post_info = $post_info;

		      $ct_result = $ct->isAllowMessage($ct_request);
		      break;

	    case 'register':
		      $stored_time = XenForo_Application::getSession()->get('ct_submit_register_time');
		      if (isset($stored_time)) {
		        $ct_submit_time = time() - $stored_time;
		      }
		      $timelabels_key = 'e_reg';
		      $ct_request->submit_time = $ct_submit_time;
		      $ct_request->tz = $spam_check['timezone'];

		      $ct_result = $ct->isAllowUser($ct_request);
		      break;

	  }
	  $ret_val = array();
	  $ret_val['ct_request_id'] = $ct_result->id;

	  if ($ct->server_change) {
		$dataRegistryModel->set('cleantalk_ws', array(
		    'work_url' => $ct->work_url,
		    'server_url' => $ct->server_url,
		    'server_ttl' => $ct->server_ttl,
		    'server_changed' => time()
		)
	    );
	  }
 
          
	// First check errstr flag.
	if (!empty($ct_result->errstr)
	      || (!empty($ct_result->inactive) && $ct_result->inactive == 1)
	) {
	    // Cleantalk error so we go default way (no action at all).
	    $ret_val['errno'] = 1;
	    // Just inform admin.
	    //$err_title = $_SERVER['SERVER_NAME'] . ' - CleanTalk hook error';
	    if (!empty($ct_result->errstr)) {
	      $ret_val['errstr'] = $this->_filterResponse($ct_result->errstr);
            }
            else {
	      $ret_val['errstr'] = $this->_filterResponse($ct_result->comment);
	    }

	    $send_flag = FALSE;

            $ct_time = $dataRegistryModel->get('cleantalk_' . $timelabels_key);
            if (!$ct_time) {
                $send_flag = TRUE;
            }
	    elseif (time() - 900 > $ct_time[0]) {
	      // 15 minutes.
	      $send_flag = TRUE;
            }

            if ($send_flag) {
                $dataRegistryModel->set('cleantalk_' . $timelabels_key, array(time()));
                
		$mail = XenForo_Mail::create('cleantalk_error', array(
			'plainText' => $ret_val['errstr'],
			'htmlText' => nl2br($ret_val['errstr'])
		));

		$mail->send($options->get('contactEmailAddress'));
	    }
	    return $ret_val;
	}

	$ret_val['errno'] = 0;
	if ($ct_result->allow == 1) {
	    // Not spammer.
	    $ret_val['allow'] = 1;
/*
	    // Store request_id in globals to store it in DB later.
	    _cleantalk_ct_result('set', $ct_result->id);
	    // Don't store 'ct_result_comment', means good comment.
*/
	}
	else {
	    // Spammer.
	    $ret_val['allow'] = 0;
	    $ret_val['ct_result_comment'] = $this->_filterResponse($ct_result->comment);

	    // Check stop_queue flag.
	    if ($spam_check['type'] == 'comment' && $ct_result->stop_queue == 0) {
	      // Spammer and stop_queue == 0 - to manual approvement.
	      $ret_val['stop_queue'] = 0;
/*
	      // Store request_id and comment in static to store them in DB later.
	      // Store 'ct_result_comment' - means bad comment.
	      _cleantalk_ct_result('set', $ct_result->id, $ret_val['ct_result_comment']);
*/
	    }
	    else {
	      // New user or Spammer and stop_queue == 1 - display form error message.
	      $ret_val['stop_queue'] = 1;
	    }
	}
	return $ret_val;
    }

    protected function _filterResponse($ct_response) {
	if (preg_match('//u', $ct_response)) {
	    $err_str = preg_replace('/\*\*\*/iu', '', $ct_response);
	}
	else {
	    $err_str = preg_replace('/\*\*\*/i', '', $ct_response);
	}
//	return filter_xss($err_str, array('a'));
	return $err_str;
    }

}
