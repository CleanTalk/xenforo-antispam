<?php
require_once XenForo_Application::getInstance()->getRootDir().'/library/CleanTalk/Base/lib/Cleantalk.php';
require_once XenForo_Application::getInstance()->getRootDir().'/library/CleanTalk/Base/lib/CleantalkHelper.php';
require_once XenForo_Application::getInstance()->getRootDir().'/library/CleanTalk/Base/lib/CleantalkRequest.php';
require_once XenForo_Application::getInstance()->getRootDir().'/library/CleanTalk/Base/lib/CleantalkRequest.php';
require_once XenForo_Application::getInstance()->getRootDir().'/library/CleanTalk/Base/lib/CleantalkSFW.php';

class CleanTalk_ControllerPublic_CleanTalkMisc extends XFCP_CleanTalk_ControllerPublic_CleanTalkMisc {

    public function actionContact() {
				
		$options = XenForo_Application::get('options');

		if($options->get('cleantalk', 'enabled_comm')){
		
			if ($options->contactUrl['type'] == 'custom')
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
					$options->contactUrl['custom']
				);
			else if (!$options->contactUrl['type'])
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
					XenForo_Link::buildPublicLink('index')
				);
			
			if ($this->_request->isPost()){
				
				if (!XenForo_Captcha_Abstract::validateDefault($this->_input))
					return $this->responseCaptchaFailed();

				$user = XenForo_Visitor::getInstance()->toArray();

				if (!$user['user_id']){
					
					$user['email'] = $this->_input->filterSingle('email', XenForo_Input::STRING);

					if (!XenForo_Helper_Email::isEmailValid($user['email']))
						return $this->responseError(new XenForo_Phrase('please_enter_valid_email'));
					
				}

				$input = $this->_input->filter(array(
					'subject' => XenForo_Input::STRING,
					'message' => XenForo_Input::STRING
				));

				if (!$user['username'] || !$input['subject'] || !$input['message'])
					return $this->responseError(new XenForo_Phrase('please_complete_required_fields'));

				$this->assertNotFlooding('contact');

	// CleanTalk Part
				
			

				$options = XenForo_Application::getOptions();
				
				$ct_authkey = $options->get('cleantalk', 'apikey');

				$dataRegistryModel = $this->getModelFromCache('XenForo_Model_DataRegistry');
				$ct_ws = $dataRegistryModel->get('cleantalk_ws');
				if (!$ct_ws) {
					$ct_ws = array(
						'work_url' => 'https://moderate.cleantalk.org',
						'server_url' => 'https://moderate.cleantalk.org',
						'server_ttl' => 0,
						'server_changed' => 0
					);
				}

				$field_name = CleanTalk_Base_CleanTalk::getCheckjsName();
				
				if (!isset($_COOKIE[$field_name]))
					$checkjs = NULL;
				elseif ($_COOKIE[$field_name] == CleanTalk_Base_CleanTalk::getCheckjsValue())
					$checkjs = 1;
				else
					$checkjs = 0;
				
				$js_timezone = (isset($_COOKIE['ct_timezone']) ? $_COOKIE['ct_timezone'] : '');
				$first_key_timestamp = (isset($_COOKIE['ct_fkp_timestamp']) ? $_COOKIE['ct_fkp_timestamp'] : '');
				$pointer_data = (isset($_COOKIE['ct_pointer_data']) ? json_decode($_COOKIE['ct_pointer_data']) : '');
				$page_set_timestamp = (isset($_COOKIE['ct_ps_timestamp']) ? $_COOKIE['ct_ps_timestamp'] : 0);			
				
				$refferrer 	= (!empty($_SERVER['HTTP_REFERER'])		? $_SERVER['HTTP_REFERER']		: null);
				$user_agent = (!empty($_SERVER['HTTP_USER_AGENT'])	? $_SERVER['HTTP_USER_AGENT']	: null);
				
				$ct = new Cleantalk();
				$ct->work_url = $ct_ws['work_url'];
				$ct->server_url = $ct_ws['server_url'];
				$ct->server_ttl = $ct_ws['server_ttl'];
				$ct->server_changed = $ct_ws['server_changed'];
				
				$options = XenForo_Application::getOptions();
				$ct_options=array(
					'enabled_reg' => $options->get('cleantalk', 'enabled_reg'),
					'enabled_comm' => $options->get('cleantalk', 'enabled_comm'),
					'apikey' => $options->get('cleantalk', 'apikey')
				);

				$sender_info = json_encode(
					array(
						'cms_lang' => 'en',
						'REFFERRER' => $refferrer,
						'post_url' => $refferrer,
						'USER_AGENT' => $user_agent,
						'ct_options' => json_encode($ct_options),
						'js_timezone' => $js_timezone,
						'mouse_cursor_positions' => $pointer_data,
						'key_press_timestamp' => $first_key_timestamp,
						'page_set_timestamp' => $page_set_timestamp,
						'cookies_enabled' => $this->_ctCookiesTest(),
						'REFFERRER_PREVIOUS' => isset($_COOKIE['ct_prev_referer']) ? $_COOKIE['ct_prev_referer'] : null,
					)
				);

				$ct_request = new CleantalkRequest();
				$ct_request->auth_key = $ct_authkey;
				$ct_request->agent = 'xenforo-26';
				$ct_request->response_lang = 'en';
				$ct_request->js_on = $checkjs;
				$ct_request->sender_info = $sender_info;
				$ct_request->sender_email = $user['email'];
				$ct_request->sender_nickname = $user['username'];
				$ct_request->sender_ip = CleantalkHelper::ip_get(array('real'), false);
				$ct_request->x_forwarded_for = CleantalkHelper::ip_get(array('x_forwarded_for'), false);
				$ct_request->x_real_ip       = CleantalkHelper::ip_get(array('x_real_ip'), false);
				
				$timelabels_key = 'e_comm';

				$ct_request->submit_time = time() - intval($page_set_timestamp);

				$ct_request->message = 	$message = json_encode(array_merge(
					array(
						'subject' => $input['subject']), 
					array(
						'message' => $input['message'])
				));
				
				// Additional info.
				$post_info = '';
				$a_post_info['comment_type'] = 'comment';

				// JSON format.
				$post_info = json_encode($a_post_info);

				if ($post_info === FALSE)
					$post_info = '';
				
				$ct_request->post_info = $post_info;

				$ct_result = $ct->isAllowMessage($ct_request);
				
				$ret_val = array();
				$ret_val['ct_request_id'] = $ct_result->id;

				if ($ct->server_change) {
					$dataRegistryModel->set('cleantalk_ws', array(
						'work_url' => $ct->work_url,
						'server_url' => $ct->server_url,
						'server_ttl' => $ct->server_ttl,
						'server_changed' => time()
					));
				}


				// First check errstr flag.
				if (!empty($ct_result->errstr) || (!empty($ct_result->inactive) && $ct_result->inactive == 1)){
					// Cleantalk error so we go default way (no action at all).
					// Just inform admin.
					
					if (!empty($ct_result->errstr))
						$ct_result->comment = $this->_filterResponse($ct_result->errstr);
					
					$send_flag = FALSE;

					$ct_time = $dataRegistryModel->get('cleantalk_' . $timelabels_key);
					
					if (!$ct_time)
						$send_flag = TRUE;
					elseif(time() - 900 > $ct_time[0])// 15 minutes.
						$send_flag = TRUE;

					if ($send_flag) {
						$dataRegistryModel->set('cleantalk_' . $timelabels_key, array(time()));
							
						$mail = XenForo_Mail::create('cleantalk_error', array(
								'plainText' => 1,
								'htmlText' => nl2br($ct_result->comment)
							)
						);

						$mail->send($options->get('contactEmailAddress'));
					}
					return parent::actionContact();
				}

				if ($ct_result->allow == 1) 
					return parent::actionContact();
				else
					return $this->responseError(new XenForo_Phrase($ct_result->comment));
							
			}else{
				return parent::actionContact();				
			}
		}
		return parent::actionContact();
    }
	
	protected function _filterResponse($ct_response) {
		
		if (preg_match('//u', $ct_response))
			$err_str = preg_replace('/\*\*\*/iu', '', $ct_response);
		else
			$err_str = preg_replace('/\*\*\*/i', '', $ct_response);
		
		return $err_str;
    }

    protected function _ctCookiesTest()
    {
        if(isset($_COOKIE['ct_cookies_test'])){
            
            $cookie_test = json_decode(stripslashes($_COOKIE['ct_cookies_test']), true);
            
            $check_srting = trim(XenForo_Application::getOptions()->get('cleantalk', 'apikey'));
            foreach($cookie_test['cookies_names'] as $cookie_name){
                $check_srting .= isset($_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] : '';
            } unset($cokie_name);
            
            if($cookie_test['check_value'] == md5($check_srting)){
                return 1;
            }else{
                return 0;
            }
        }else{
            return null;
        }    	
    }

}
