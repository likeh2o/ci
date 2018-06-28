<?php
// 解锁
function shut_down_for_lock($fp_lock)
{
    flock($fp_lock, LOCK_UN);
    fclose($fp_lock);
}

/**
 * Class Task_Controller
 */
abstract class Task_Controller extends CI_Controller
{
    protected $userInfo         = null;
    private $URL_EXT_CALL_API = ['html'];
    private $smarty_tpl;
    protected $from_type = '';
    public function __construct()
    {
        parent::__construct();
        if (!is_cli()) {
            $this->load->library('smarty');
            $this->load->library('session');
            $this->load->model('User_model');

            $this->setFromType();
        }
        $this->load->model('DIY_model');
        $this->load->library('Logic/User/Corp_User');
        $this->load->library('Logic/Common/Tag');
        $this->load->helper('diy');
        require_once 'Inner_Model.php';
    }

    public function _remap($functionName, $params)
    {
        try {
            call_user_func_array(array($this, $functionName), $params);
        } catch (Exception $e) {
            log_message('error', json_encode($e->getTrace()[0]));
            throw $e;
        }
    }

    public function __call($functionName, $args)
    {
        $host = $this->getHostName();
        $api  = $this->getApi($host);
        if (!empty($api) && $api != REQUEST_TPL) {
            $data = $this->callInnerApi();
        } else {
            $data = new stdClass();
        }

        $data = $this->initPageVars($data);
        $this->display($data);
    }

    private function initPageVars($data)
    {
        $pageVars = array();
        if (method_exists($this, 'setPageVars')) {
            $pageVars = $this->setPageVars();
        }

        if (!empty($pageVars) && is_object($data)) {
            $data->page_vars = $pageVars;
        }

        return $data;
    }

    /**
     * callInnerApi
     *
     * @param bool $params 接口参数:拦截之后，需要传递参数
     *
     * @param bool $ms
     * @param int  $timeout
     * @param int  $timeout_ms
     *
     * @return
     * @access private
     */
    protected function callInnerApi($params = null, $ms = true, $timeout = 2, $timeout_ms = 2000)
    {
        $host   = $this->getHostName();
        $method = strtolower($_SERVER['REQUEST_METHOD']);
        $api    = $this->getApi($host);

        if (is_null($params)) {
            $params = $this->input->{$method}(null, true);
        }

        return $this->callInnerApiDiy($host, $api, $params, $method, $ms, $timeout, $timeout_ms);
    }

    protected function callInnerApiDiy($host, $api, $params, $method, $ms = true, $timeout = 2, $timeout_ms = 2000, $is_internal_api = true)
    {
        $this->load->model('Inner_Model');
        $result = $this->Inner_Model->callApi($host, $api, $params, $method, $ms, $timeout, $timeout_ms, $is_internal_api);

        return $result;
    }

    protected function callExternalApiDiy($host, $api, $params, $method, $headers = array(), $ms = true, $timeout = 2, $timeout_ms = 2000, $is_internal_api = false)
    {
        $this->load->model('Inner_Model');
        $result = $this->Inner_Model->callApi($host, $api, $params, $method, $ms, $timeout, $timeout_ms, $is_internal_api, $headers);

        return $result;
    }

    private function getApi($host)
    {
        $api_key        = implode('/', $this->uri->rsegments);
        $allowedApiList = $this->getAllowedApiList();
        if (!is_array($allowedApiList)) {
            exit('getAllowedApiList must return array');
        }

        $apis = array();
        if (!empty($allowedApiList) && !empty($host)) {
            if (!array_key_exists($host, $allowedApiList)) {
                exit('api host not allowed');
            }
            $apis = $allowedApiList[$host];
        }

        $api = isset($apis[$api_key]) ? $apis[$api_key] : '';
        if (empty($api)) {
            exit('api not allowed');
        }

        return $api;
    }

    // 待删除，不要使用
    protected function errorInfo($info, $code = -1)
    {
        exit(json_encode(array('error' => $code, 'info' => $info)));
    }

    protected function displayError($info, $code = -1)
    {
        log_message('error', $info);
        exit(json_encode(array('error' => $code, 'info' => $info)));
    }

    protected function isWeixinClient()
    {
        $userAgent = addslashes($_SERVER['HTTP_USER_AGENT']);
        if (strpos($userAgent, 'MicroMessenger') === false && strpos($userAgent, 'Windows Phone') === false) {
            return false;
        }

        return true;
    }

    protected function getSmartyDefaultTplPath()
    {
        return $this->smarty->template_dir[0] . $this->router->directory . $this->router->class;
    }

    protected function setSmartyTpl($tpl)
    {
        $this->smarty_tpl = $tpl;
    }
    protected function display($data = array())
    {
        if (is_cli()) {
            exit(json_encode($data));
        }

        if (false === $data) {
            $this->displayError('系统异常，请重试');
        }

        $template_dir = $this->smarty->template_dir[0];
        if (empty($this->smarty_tpl)) {
            $tpl = $this->router->directory . $this->router->class . '/' . $this->router->method . '.tpl';
        } else {
            $tpl = $this->smarty_tpl .'.tpl';
            $this->smarty_tpl = '';
        }
        if (!file_exists($template_dir . $tpl) || $this->input->get('debugo')) {
            echo json_encode($data);
            exit;
        }

        if (defined('SMARTY_DEBUG') && SMARTY_DEBUG) {
            $this->smarty->assign('CI', '为方便debug，把该变量清空');
            $this->smarty->debugging = true;
        }

        $data = $this->initPageVars($data);
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $this->smarty->assign($k, $v);
            }
        }

        $this->initDomains();

        $this->smarty->display($tpl);
        exit;
    }

    private function initDomains(){
        $appId = explode('.', $_SERVER['HTTP_HOST'])[0];
        $this->smarty->assign('USER_DOMAIN', 'http://'.$appId.USER_DOMAIN_SUF);
        $this->smarty->assign('CONTEST_DOMAIN', 'http://'.$appId.CONTEST_DOMAIN_SUF);
        $this->smarty->assign('BOSS_DOMAIN', BOSS_SITE_DOMAIN);
        $this->smarty->assign('MANAGER_DOMAIN', MANAGER_SITE_DOMAIN);
        $this->smarty->assign('INNOSPACE_PC_DOMAIN', 'http://www'.GLOBAL_COOKIE_DOMAIN);
        $this->smarty->assign('USER_DOMAIN_PC', 'http://'.WEIXIN_APPID_LEWUHUI.USER_DOMAIN_SUF);
        $this->smarty->assign('CONTEST_DOMAIN_PC', 'http://'.WEIXIN_APPID_LEWUHUI.CONTEST_DOMAIN_SUF);
    }

    /*{{{ 参数处理*/
    protected function get_check($key, $check, $type = PARAM_TYPE_STRING, $xss_clean = true)
    {
        $val = $this->input->get($key, $xss_clean);

        return $this->params_check($key, $check, $type, $val, METHOD_GET);
    }

    protected function post_check($key, $check, $type = PARAM_TYPE_STRING, $xss_clean = true)
    {
        $val = $this->input->post($key, $xss_clean);

        return $this->params_check($key, $check, $type, $val, METHOD_POST);
    }

    private function params_type_check($key, $type, $val)
    {
        switch ($type) {
            case PARAM_TYPE_NUMBER:
                if (!is_numeric($val)) {
                    $this->response_error(Error_Code::ERROR_PARAM_INT, $key);
                }
                $val = (float)$val;
                break;
            case PARAM_TYPE_INT:
                if (!is_numeric($val)) {
                    $this->response_error(Error_Code::ERROR_PARAM_INT, $key);
                }
                $val = (int)$val;
                break;
            default:
                break;
        }
        return $val;
    }
    private function params_check($key, $check, $type, $val, $method)
    {
        if (null === $val) {
            $val = false;
        }
        if (false !== $val && !is_numeric($val) && !is_string($val)) {
            $this->response_error(Error_Code::ERROR_PARAM, $key);
        }
        $val_origin = $val;
        $val        = trim($val);

        switch ($check) {
            case PARAM_NOT_NULL_NOT_EMPTY:
                if (empty($val)) {
                    $this->response_error(Error_Code::ERROR_PARAM, $key . ' 必传非空');
                }
                $val = $this->params_type_check($key, $type, $val);
                break;
            case PARAM_NOT_NULL_EMPTY:
                if (false === $val_origin) {
                    $this->response_error(Error_Code::ERROR_PARAM, $key . ' 必传');
                }
                $val = $this->params_type_check($key, $type, $val);
                break;
            case PARAM_NULL_NOT_EMPTY:
                if (false !== $val_origin) {
                    if (empty($val)) {
                        $this->response_error(Error_Code::ERROR_PARAM, $key . ' 若传非空');
                    }
                    $val = $this->params_type_check($key, $type, $val);
                }
                break;
            case PARAM_NULL_EMPTY:
                if (false !== $val_origin && !empty($val)) {
                    $val = $this->params_type_check($key, $type, $val);
                }
                break;
        }

        $this->param_list[$method][$key] = array($val_origin, $val);

        return $val;
    }
    /*}}}*/

    /*{{{ 接口模式处理*/
    protected function response($data)
    {
        unset($data->cost);
        $data->info = empty($data->info)?'':$data->info;
        exit(json_encode($data));
    }

    protected function response_error($code, $info = null, $result_ext = array())
    {
        $error = '';
        if (class_exists('Error_Code')) {
            $error = Error_Code::desc($code);
        }
        $info = $info ?: $error;
        $this->load->library('Api_spec');
        $data = $this->api_spec->gen_error($code, $info);
        if (!empty($result_ext)) {
            $data->result = $result_ext;
        }

        return $this->response($data);
    }

    protected function response_object($object)
    {
        $data = $this->api_spec->gen_object($object);

        return $this->response($data);
    }

    protected function response_insert($lastid)
    {
        $data = $this->api_spec->gen_insert($lastid);

        return $this->response($data);
    }

    protected function response_data($data = array(), $ext = array())
    {
        $data = $this->api_spec->gen_data($data, $ext);

        return $this->response($data);
    }

    protected function response_list($list, $total = 0, $page = 1, $size = 10, $ext = array())
    {
        $data = $this->api_spec->gen_list($list, $total, $page, $size, $ext);

        return $this->response($data);
    }

    protected function response_update($affected_rows)
    {
        $data = $this->api_spec->gen_update($affected_rows);

        return $this->response($data);
    }

    protected function response_string($data)
    {
        $data = $this->api_spec->gen_string($data);
        return $this->response($data);
    }

    protected function response_empty()
    {
        $data = $this->api_spec->gen_empty();
        return $this->response($data);
    }
    /*}}}*/

    /*{{{ 公众号跳转逻辑 */
    /**
     * needLogin
     *  登录校验
     * toLogin m站模式下是否直接到登录页面
     *
     * @access protected
     */
    protected function needLogin($toLogin = 0, $scope = WEIXIN_SNSAPI_SCOPE_BASE)
    {
        $this->userInfo = $this->User_model->getUserInfo();
        if ($this->isWeixinClient()) {
            if (empty($this->userInfo)) {
                $this->readyGetOpenid($scope);
            }
        }else{
            if($toLogin){
                if(empty($this->userInfo)){
                    $this->readyH5Login();
                }
            }
        }
        return $this->userInfo;
    }

    protected function needBindMobile(){
        if(empty($this->userInfo->mobile)){
            $need_mobile = 1;
            $this->readyH5Login($need_mobile);
        }
    }

    /**
     * needLoginForBoth
     *  微信、h5 通用登录逻辑
     * @access protected
     * @return void
     */
    protected function needLoginForBoth($toLogin = 0)
    {
        if ($this->isWeixinClient()) {
            $this->needLogin($toLogin);
        }
    }

    /**
     * needLoginJson
     *  登录校验（json）
     *
     * @access protected
     * @return void
     */
    protected function needLoginJson()
    {
        $this->userInfo = $this->User_model->getUserInfo();
        if (empty($this->userInfo)) {
            $this->displayError('您还没有登录，请先登录', -10);
        }
    }

    /**
     * readyGetOpenid
     *  准备发起微信授权
     *
     * @access protected
     * @return void
     */
    protected function readyGetOpenid($scope = WEIXIN_SNSAPI_SCOPE_BASE)
    {
        $curUrl       = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $url          = $this->BASE_SITE_URL . '/user/login?url=' . urlencode($curUrl);
        $redirect_uri = WEIXIN_REDIRECT_PROXY_URL . urlencode($url);
        $appid        = $this->getAppId();

        $url = WEIXIN_SNSAPI_AUTHORIZE_URL . '?appid=' . $appid;
        $url .= '&redirect_uri=' . urlencode($redirect_uri);
        $url .= '&response_type=code&scope=' . $scope;
        $url .= '&state=' . $scope . '&component_appid=' . COMPONENT_APPID;
        $url .= '#wechat_redirect';

        header('Location: ' . $url);
        exit;
    }
    
    /**
     * PC 强授权登录
     * 2018-1-31
     * @param  string $appid [description]
     * @return [type]        [description]
     */
    protected function readyPcOpenid($appid = '')
    {
        if(empty($appid))
        {
            show_error('appid参数错误', '500', $heading = '');
            exit;
        }
        $curUrl       = 'http://' . $_SERVER['HTTP_HOST'];
        $url          = 'http://' . $_SERVER['HTTP_HOST'] . '/user/pclogin?url=' . urlencode($curUrl);
        $redirect_uri = WEIXIN_REDIRECT_SITES_PROXY_URL . urlencode($url);
        $appid        = $appid;
        
        //PC
        $url = WEIXIN_SNSAPI_QRCONNECT_URL . '?appid=' . $appid;
        $url .= '&redirect_uri=' . urlencode($redirect_uri);
        $url .= '&response_type=code&scope=' . WEIXIN_SNSAPI_SCOPE_LOGIN;
        $url .= '&state=' . WEIXIN_SNSAPI_SCOPE_LOGIN;
        $url .= '&device=pc#wechat_redirect';

        header('Location: ' . $url);
        exit;
    }

    // need_mobile = 1 用户没有手机号，需要绑定手机号
    protected function readyH5Login($need_mobile = 0){
        $curUrl = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $redirect_uri = urlencode($curUrl);

        $url = 'http://'. WEIXIN_APPID_LEWUHUI . USER_DOMAIN_SUF. '/login?redirect_uri=' . $redirect_uri;
        if(!empty($need_mobile)){
            $url .= '&need_mobile=1';
        }

        header('Location: ' . $url);
        exit;
    }

    protected function getAppId()
    {
        // 默认是appid
        $domain_pre = explode('.', $_SERVER['HTTP_HOST'])[0];

        if (defined('DOMAIN_PRE') && strpos(DOMAIN_PRE, $domain_pre) !== false) {
            $appId = WEIXIN_APPID_LEWUHUI;
        } else {
            $appId = $domain_pre;
        }

        return $appId;
    }

    /**
     * login
     *  微信授权之后，跳转到该页面，获取用户微信信息并登录
     *
     * @access public
     * @return void
     */
    protected function login()
    {
        // 输入数据判断
        //url获取需要去掉xss_clean的过滤，因为一旦url中像这样包含redirect=[url]之类的参数，并且[url]中包含有&符号的话，就会出现下面的状况
        //order?redirect=http%3A%2F%2Fwx11041d5d1fbbb317.contest.weizheng.innospace.cn%2Forder%2Fpay_result%3Fout_trade_no%3D000000%26r%3D82&time=1480320763
        //就会被转换为
        //order?redirect=http://wx11041d5d1fbbb317.contest.weizheng.innospace.cn/order/pay_result?out_trade_no=000000&r=82&time=1480320763
        //redirect参数的&r=82因为被decode，所以再跳转时就不能算是redirect的内容了
        $toUrl       = $this->input->get('url');
        $wxAuthCode  = $this->input->get('code', true);
        $wxAuthState = $this->input->get('state', true);
        if (empty($toUrl) || empty($wxAuthCode) || empty($wxAuthState)) {
            // log_message('error', '登录失败（-1）');
            show_error('登录失败（-1）', '500', $heading = '');
        }
        // 公众号信息
        $appId = $this->getAppId();

        $params = array(
            'authorizer_appid' => $appId,
        );
        $componentAuthInfo = $this->callInnerApiDiy(
            API_HOST_WEIXIN,
            'component/authorizer/get.json',
            $params,
            METHOD_GET
        );
        if (empty($componentAuthInfo) || empty($componentAuthInfo->result)) {
            log_message('error', '登录失败（-2）| $componentAuthInfo=' . json_encode($componentAuthInfo) . '| params=' . json_encode($params));
            show_error('登录失败（-2）', '500', $heading = '');
        }

        // 获取用户信息
        $params   = array(
            'apppk'   => $componentAuthInfo->result->pk_component_authorizer_app,
            'code'    => $wxAuthCode,
            'snsapi'  => $wxAuthState,
        );
        $userInfo = $this->callInnerApiDiy(API_HOST_USER, 'user/get_by_wxcode.json', $params, METHOD_GET, false);
        if (empty($userInfo) || $userInfo->error != 0 || empty($userInfo->result)) {
            // log_message('error', '登录失败（-3）');
            show_error('登录失败（-3）', '500', $heading = '');
        }

        $user_id           = $userInfo->result->pk_user;
        $uuid              = $userInfo->result->uuid;
        $openid            = $userInfo->result->ext_weixin->openid;
        $mobile            = $userInfo->result->mobile;
        $user_corp_id      = $userInfo->result->user_corp_id;
        $user_platform_id  = $userInfo->result->user_platform_id;
        $authorizer_app_id = $componentAuthInfo->result->pk_component_authorizer_app;
        $qrcode_url        = $componentAuthInfo->result->qrcode_url;
        $corp_id           = $componentAuthInfo->result->fk_corp;
        $this->User_model->setUserInfo(
            $user_id, $uuid, $openid, $authorizer_app_id, $corp_id, $mobile, 
            $qrcode_url, $user_corp_id, $user_platform_id
        );
        header('Location: ' . $toUrl);
        exit();
    }
    
    /**
     * 微信网站登录
     * 2018-1-31
     * @return [type] [description]
     */
    protected function pclogin()
    {

        $toUrl       = $this->input->get('url');
        $wxAuthCode  = $this->input->get('code', true);
        $wxAuthState = $this->input->get('state', true);
        $device      = $this->input->get('device',true);

        if (empty($toUrl) || empty($wxAuthCode) || empty($wxAuthState)) {
            show_error('登录失败（-1）', '500', $heading = '');
        }
        // 英诺网站平台测试
        $appId = WEIXIN_OPEN_INNO_APP_APPID;

        $params = array(
            'authorizer_appid' => $appId,
        );
        $componentAuthInfo = $this->callInnerApiDiy(
            API_HOST_WEIXIN,
            'component/authorizer/get.json',
            $params,
            METHOD_GET
        );
        if (empty($componentAuthInfo) || empty($componentAuthInfo->result)) {
            log_message('error', '登录失败（-2）| $componentAuthInfo=' . json_encode($componentAuthInfo) . '| params=' . json_encode($params));
            show_error('登录失败（-2）', '500', $heading = '');
        }

        // 获取用户信息
        $params   = array(
            'apppk'   => $componentAuthInfo->result->pk_component_authorizer_app,
            'code'    => $wxAuthCode,
            'snsapi'  => $wxAuthState,
        );
        $userInfo = $this->callInnerApiDiy(API_HOST_USER, 'user/get_by_wxcode_pc.json', $params, METHOD_GET, false);
        if (empty($userInfo) || $userInfo->error != 0 || empty($userInfo->result)) {
            // log_message('error', '登录失败（-3）');
            show_error('登录失败（-3）', '500', $heading = '');
        }
        $user_id           = $userInfo->result->pk_user;
        $uuid              = $userInfo->result->uuid;
        $openid            = $userInfo->result->ext_weixin->openid;
        $mobile            = $userInfo->result->mobile;
        $user_corp_id      = $userInfo->result->user_corp_id;
        $user_platform_id  = $userInfo->result->user_platform_id;
        $authorizer_app_id = $componentAuthInfo->result->pk_component_authorizer_app;
        $qrcode_url        = $componentAuthInfo->result->qrcode_url;
        $corp_id           = $componentAuthInfo->result->fk_corp;
        $user_avatar       = $userInfo->result->avatar;

        $_SESSION['appId'] = $appId;

        $this->User_model->setUserInfo(
            $user_id, $uuid, $openid, $authorizer_app_id, $corp_id, $mobile, 
            $qrcode_url, $user_corp_id, $user_platform_id,$user_avatar
        );
        header('Location: ' . $toUrl);
        exit();
    }
    /*}}}*/

/*{{{ sdk sign*/
    /**
     * get_jssdk_sign
     *  微信jssdk处理
     *
     * @access protected
     */
    protected function getJssdkSign($apppk = 0, $url = '')
    {
        if (!$this->isWeixinClient()) {
            return array(
                'appid' => '',
                'timestamp' => '',
                'noncestr' => '',
                'signature' => '',
            );
        }
        if (empty($apppk)) {
            $userInfo = $this->User_model->getUserInfo();
            $apppk    = $userInfo->apppk;
        }

        if (empty($url)) {
            $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
            $url      = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }

        $params      = array(
            'apppk' => $apppk,
            'url'   => $url,
        );
        $jsApiConfig = $this->callInnerApiDiy(
            API_HOST_WEIXIN,
            'component/authorizer/get_jsapi_config',
            $params,
            METHOD_GET
        );
        if (empty($jsApiConfig) || $jsApiConfig->error != 0 || empty($jsApiConfig->result)) {
            return array(
                'appid' => '',
                'timestamp' => '',
                'noncestr' => '',
                'signature' => '',
            );
        }

        return (array)$jsApiConfig->result;
    }

    protected function getCardsdkSign($apppk, $card_id =null, $code = null, $openid = null, $balance = null, $card_type = null, $location_id = null, $appid = null)
    {
        $params = compact('apppk');
        empty($card_id) or $params['card_id'] = $card_id;
        empty($code) or $params['code'] = $code;
        empty($openid) or $params['openid'] = $openid;
        empty($balance) or $params['balance'] = $balance;
        empty($card_type) or $params['card_type'] = $card_type;
        empty($location_id) or $params['location_id'] = $location_id;
        empty($appid) or $params['appid'] = $appid;

        $cardApiConfig = $this->callInnerApiDiy(API_HOST_WEIXIN, 'component/authorizer/get_cardapi_config.json',
            $params, METHOD_GET);


        if (empty($cardApiConfig) || $cardApiConfig->error != 0 || empty($cardApiConfig->result)) {
            return array();
        }

        $this->load->helper('diy');
        return obj2array($cardApiConfig->result);
    }

    /*}}}*/

    /*{{{ 用户登录逻辑-输出data一并提供 */
    protected function getCaptcha()
    {
        //创建图片，定义颜色值
        header('Content-type: image/PNG');
        $options = array(
            'width'     => 100,
            'height'    => 42,
            'content'   => 1,
            'lineWidth' => 1,
        );
        $this->load->library('Captcha/SimpleCaptcha', $options); //图片验证生成
        // $simpleCaptcha = new SimpleCaptcha($options);
        $this->simplecaptcha->ShowImage();
        $captchaCode = $this->simplecaptcha->GetCaptchaText();

        $this->User_model->setCaptchaCode($captchaCode);
        exit();
    }

    protected function sendSmsCode($client_id, $mobile, $captcha, $apppk, $sign = null, $biz = '')
    {
        if (!$this->User_model->checkCaptchaCode($captcha)) {
            $this->displayError('图形验证码有误', -3);
        }

        $this->User_model->setVerifyPhone($mobile);

        $this->load->library('SmsService');
        $result = $this->smsservice->sendCode($client_id, $mobile, $apppk, $sign, $biz);
        if (false === $result || $result->error != 0) {
            $this->displayError('发送验证码失败', $result->error);
        }

        return $result;
    }

    protected function verifySmsCode($client_id, $mobile, $captcha, $code)
    {
        if (!$this->User_model->checkPhone($mobile)) {
            $this->displayError('手机号有误', -1);
            log_message('error', '手机号有误'.json_encode($mobile));
        }

        if (!$this->User_model->checkCaptchaCode($captcha)) {
            $this->displayError('图形验证码有误', -3);
        }

        $this->load->library('SmsService');
        $result = $this->smsservice->verifyCode($client_id, $mobile, $code);
        if (false === $result) {
            $this->displayError('服务器错误');
        }

        if ($result->error != 0) {
            $this->displayError('短信验证码有误');
            log_message('error', '短信验证码有误'.json_encode($mobile));
        }

        return $result;
    }

    protected function bindMobile($mobile, $apppk, $corp_id)
    {
        if ($this->isWeixinClient()) {
            $this->userInfo = $this->User_model->getUserInfo();
            //规避PC应用登录在微信环境下错误，直接走非微信环境的逻辑
            if($apppk != USER_INNOAPP_APPPK)
            {
               $this->wxBindMobile($this->userInfo, $mobile, $apppk, $corp_id);
            }
            else
            {
               $this->regMobile($mobile, $apppk, $corp_id);
            }
            
        } else {
            //微信第三方登录的情况，也是微信绑定
            $this->userInfo = $this->User_model->getUserInfo();
            if(!empty($this->userInfo) || !empty($this->userInfo->openid))
            {
               $this->wxBindMobile($this->userInfo, $mobile, $apppk, $corp_id);
            }
            else
            {
                $this->regMobile($mobile, $apppk, $corp_id);
            }
        }
        $this->userInfo = $this->User_model->getUserInfo();

        return true;
    }

    private function wxBindMobile($userInfo, $mobile, $apppk, $corp_id)
    {
        $user_id = $userInfo->uid;
        $qrcode_url = $userInfo->qrcode_url;
        $params['uid']    = $user_id;
        $params['mobile'] = $mobile;
        $params['apppk']  = $apppk;
        $result           = $this->callInnerApiDiy(API_HOST_USER, 'user/bind_mobile.json', $params, METHOD_POST);
        if (false === $result || $result->error != 0) {
            if($result->error == -107){
                $this->displayError('手机号已被其他用户绑定', $result->error);
                log_message('error', '手机号已被其他用户绑定'.json_encode($mobile));
            }else{
                $this->displayError('绑定手机号失败', $result->error);
                log_message('error', '绑定手机号失败'.json_encode($mobile));
            }
        }

        $params = array();
        $params['uid'] = $user_id;
        $params['ext_mobile'] = 1;
        $params['ext_weixin'] = 1;
        $result = $this->callInnerApiDiy(API_HOST_USER, 'user/get_by_id.json', $params, METHOD_GET);
        if (empty($result) || empty($result->result->mobile)) {
            $this->displayError('用户没有手机号');
            log_message('error', '用户没有手机号'.$user_id);
        }

        $uid = $result->result->pk_user;
        $uuid = $result->result->uuid;
        $openid = $result->result->ext_weixin->openid;
        $user_corp_id = $result->result->user_corp_id;
        $user_platform_id = $result->result->user_platform_id;
        $this->User_model->setUserInfo(
            $uid,
            $uuid,
            $openid,
            $apppk,
            $corp_id,
            $mobile,
            $qrcode_url,
            $user_corp_id,
            $user_platform_id
        );
    }

    private function regMobile($mobile, $apppk, $corp_id)
    {
        $reg_channel = USER_REG_CHANNEL_MOBILE;
        $params = compact('mobile', 'reg_channel', 'corp_id');
        $result = $this->callInnerApiDiy(API_HOST_USER, 'user/get_by_channel_mobile.json', $params, METHOD_GET);
        if (false === $result || $result->error != 0) {
            $this->displayError('获取手机号信息错误');
            log_message('error', '获取手机号信息错误'.json_encode($mobile));
        }

        if (empty($result->result)) {
            $params = compact('mobile', 'corp_id');
            $result = $this->callInnerApiDiy(API_HOST_USER, 'user/reg_mobile.json', $params, METHOD_POST);
            if (false === $result || $result->error != 0) {
                $this->displayError('手机号注册失败');
                log_message('error', '手机号注册失败'.json_encode($mobile));
            }

            $uid = $result->result->pk_user;
            $uuid = $result->result->uuid;
            $user_corp_id = $result->result->user_corp_id;
            $user_platform_id = $result->result->user_platform_id;
        } else {
            $uid = $result->result->user_id;
            $uuid = $result->result->uuid;
            $user_corp_id = $result->result->user_corp_id;
            $user_platform_id = $result->result->user_platform_id;
        }

        $this->User_model->setUserInfo(
            $uid,
            $uuid,
            '',
            $apppk,
            $corp_id,
            $mobile,
            '',
            $user_corp_id,
            $user_platform_id
        );
    }

    protected function checkUserMobile()
    {
        $this->needLoginJson();
        $params = array();
        $params['uid'] = $this->userInfo->uid;
        $params['ext_mobile'] = 1;
        $user = $this->callInnerApiDiy(API_HOST_USER, 'user/get_by_id.json', $params, METHOD_GET);
        if (empty($user) || empty($user->result->mobile)) {
            $this->displayError('用户没有手机号');
            log_message('error', '用户没有手机号'.json_encode($this->userInfo));
        }

        // 新用户这里是需要重新设置的
        $this->User_model->setUserInfoMobile($user->result->mobile);
        $this->User_model->setUserIdCorp($user->result->user_corp_id);
        $this->User_model->setUserIdPlatform($user->result->user_platform_id);
        $this->userInfo = $this->User_model->getUserInfo();

        $this->display(array('error'=>0));
    }

    protected function getApppk()
    {
        $userInfo = $this->User_model->getUserInfo();
        if (!empty($userInfo)) {
            return $userInfo->apppk;
        }
        
        if($_SESSION['appId'] == WEIXIN_OPEN_INNO_APP_APPID)
        {
            return USER_INNOAPP_APPPK;
        }

        if (!$this->isWeixinClient()) {
            return USER_APPPK;
        }
    }

    protected function getCorpId()
    {
        $userInfo = $this->User_model->getUserInfo();
        if (!empty($userInfo)) {
            return $userInfo->corp_id;
        }
        if($_SESSION['appId'] == WEIXIN_OPEN_INNO_APP_APPID)
        {
            return USER_CORP;;
        }
        if (!$this->isWeixinClient()) {
            return USER_CORP;
        }
    }

    /*}}}*/

    /*{{{ FROM_TYPE 逻辑*/
    private function setFromType()
    {
        $domain_pre = explode('.', $_SERVER['HTTP_HOST'])[0];
        if ($domain_pre == FROM_TYPE_MALL) {
            $this->from_type = FROM_TYPE_MALL;
        } elseif ($domain_pre == FROM_TYPE_MI) {
            $this->from_type = FROM_TYPE_MI;
        }
    }

    protected function getPriceShow($price, $num = 1)
    {
        if ($this->from_type == FROM_TYPE_MALL) {
            $price_show = calc_point_from_rmb($price, VIRTUAL_CURRENCY_CASH) * $num;
            return sprintf('%.2f', $price_show/100);
        }

        if ($this->from_type == FROM_TYPE_MI) {
            $price_show = calc_point_from_rmb($price, VIRTUAL_CURRENCY_MI) * $num;
            return $price_show/100;
        }

        $price_show  = $price * $num;
        return sprintf('%.2f', $price_show/100);
    }

    protected function getOrderPriceShow($price, $order_from_type = '', $num = 1)
    {
        if ($order_from_type == ORDER_FROM_TYPE_MALL) {
            $price_show = calc_point_from_rmb($price, VIRTUAL_CURRENCY_CASH) * $num;
            return sprintf('%.2f', $price_show/100);
        }

        if ($order_from_type == ORDER_FROM_TYPE_MI) {
            $price_show = calc_point_from_rmb($price, VIRTUAL_CURRENCY_MI) * $num;
            return $price_show/100;
        }

        $price_show  = $price * $num;
        return sprintf('%.2f', $price_show/100);
    }

    protected function getOrderPriceUnit($order_from_type)
    {
        if ($order_from_type == ORDER_FROM_TYPE_MALL) {
            return CURRENCY_NAME_RMB;
        }

        if ($order_from_type == ORDER_FROM_TYPE_MI) {
            return CURRENCY_NAME_MI;
        }

        return CURRENCY_NAME_RMB;
    }

    protected function getPriceUnit()
    {
        if ($this->from_type == FROM_TYPE_MALL) {
            return CURRENCY_NAME_RMB;
        }

        if ($this->from_type == FROM_TYPE_MI) {
            return CURRENCY_NAME_MI;
        }

        return CURRENCY_NAME_RMB;
    }

    protected function getBookDomain()
    {
        if ($this->from_type == FROM_TYPE_MALL) {
            return 'http://' . BOOK_PLATFORM_DOMAIN_SUF_MALL;
        }

        if ($this->from_type == FROM_TYPE_MI) {
            return 'http://' . BOOK_PLATFORM_DOMAIN_SUF_MI;
        }
    }

    protected function getGoodsDomain()
    {
        if ($this->from_type == FROM_TYPE_MALL) {
            return 'http://' . GOODS_DOMAIN_SUF_MALL;
        }

        if ($this->from_type == FROM_TYPE_MI) {
            return 'http://' . GOODS_DOMAIN_SUF_MI;
        }
    }

    protected function getMovieDomain()
    {
        if ($this->from_type == FROM_TYPE_MALL) {
            return 'http://' . MOVIE_DOMAIN_SUF_MALL;
        }

        if ($this->from_type == FROM_TYPE_MI) {
            return 'http://' . MOVIE_DOMAIN_SUF_MI;
        }
    }

    protected function getTicketDomain()
    {
        if ($this->from_type == FROM_TYPE_MALL) {
            return 'http://' . TICKET_DOMAIN_SUF_MALL;
        }

        if ($this->from_type == FROM_TYPE_MI) {
            return 'http://' . TICKET_DOMAIN_SUF_MI;
        }
    }

    protected function getOrderFromType()
    {
        if ($this->from_type == FROM_TYPE_MALL) {
            return ORDER_FROM_TYPE_MALL;
        }

        if ($this->from_type == FROM_TYPE_MI) {
            return ORDER_FROM_TYPE_MI;
        }
        return ORDER_FROM_TYPE_DEFAULT;
    }

    protected function getMallSiteDomain()
    {
        if ($this->from_type == FROM_TYPE_MALL) {
            return 'http://' . MALL_SITE_DOMAIN_MALL;
        }

        if ($this->from_type == FROM_TYPE_MI) {
            return 'http://' . MALL_SITE_DOMAIN_MI;
        }
        // 各个业务自由逻辑
        return '/';
    }

    protected function isFromMall()
    {
        if ($this->from_type == FROM_TYPE_MALL) {
            return 1;
        }

        if ($this->from_type == FROM_TYPE_MI) {
            return 1;
        }
        return 0;
    }
    /*}}}*/

    /*{{{ 进程锁定逻辑*/
    protected function isSingleProcess()
    {
        // 文件唯一标识
        $file = $_SERVER['PWD'] .'/'. $_SERVER['argv'][0] .'/'. $_SERVER['argv'][1];
        $lock_file = WORKSPACE . '/logs/' . md5($file).'.lock';

        $fp_lock = fopen($lock_file, 'w');
        if (!flock($fp_lock, LOCK_EX | LOCK_NB)) {
            exit('该命令不能并发：'.$file);
        }
        register_shutdown_function('shut_down_for_lock', $fp_lock);
    }
    /*}}}*/

    protected function getFooterNav($active = ''){
        if(empty($active) && !empty($_SESSION['service_type'])){
            $active = ($_SESSION['service_type'] == SERVICE_TYPE_CONTEST)?'hots':'shops';
        }
        $params = array();
        $params['active'] = $active;
        return $this->smarty->fetch(dirname(SYSTEM_PATH).'/tpl/footerNav.tpl', $params);
    }

    protected function getFixNav(){
        return $this->smarty->fetch(dirname(SYSTEM_PATH).'/tpl/fixNav.tpl');
    }
    
    protected function _getVueMain(){
        return $this->smarty->fetch(dirname(SYSTEM_PATH).'/tpl/lewuhui/vue_main.tpl');
    }

    abstract protected function getHostName();

    abstract protected function getAllowedApiList();



    protected function manager_verify_corp_user_bind_mobile()
    {
        $verify = $this->verifyCorpUserBindMobile();

        $result = [
            'error' => 0,
            'verify' => $verify
        ];
        $this->display($result);
    }

    protected function captcha()
    {
        return $this->getCaptcha();
    }

    protected function manager_send_sms_verify_code()
    {
        $mobile = $this->input->post('mobile', true);
        $captcha = $this->input->post('captcha', true);

        $result = $this->sendSmsCode(SMS_CLIENT_ID_CONTEST, $mobile, $captcha, 0, SMS_SIGN_INNOSPACE);

        $this->display($result);
    }

    protected function manager_corp_user_bind_mobile()
    {
        $mobile = $this->input->post('mobile', true);
        $captcha = $this->input->post('captcha', true);
        $verify_code = $this->input->post('verify_code', true);

        $this->verifySmsCode(SMS_CLIENT_ID_CONTEST, $mobile, $captcha, $verify_code);

        $result = $this->corp_user->bindMobile($this->userInfo->pk_corp, $this->userInfo->user_id, $mobile);

        $this->display($result);
    }

    protected function verifyCorpUserBindMobile()
    {
        if (!empty($this->userInfo->mobile) && !empty($this->userInfo->uid_corp) && !empty($this->userInfo->uid_platform)) {
            return true;
        }

        $corp_user_info = $this->corp_user->getByPk($this->userInfo->pk_corp_user);
        if (empty($corp_user_info->data)) {
            log_message('error', 'get corp_user by pk fail, pk=' . $this->userInfo->pk_corp_user);
            return false;
        }

        $this->userInfo->uid_corp = $corp_user_info->data->fk_user_corp;
        $this->userInfo->uid_platform = $corp_user_info->data->fk_user_platform;
        $this->userInfo->mobile = $corp_user_info->data->mobile;

        $this->User_model->setCorpUserInfo($this->userInfo);

        return !empty($this->userInfo->mobile);
    }


    protected function encryptOrderCodeData($verify_code)
    {
        //当前时间戳
        $cur_timestamp = time();
        //带签名数据
        $toBeSignData = array($cur_timestamp, $verify_code, ORDER_ENCRYPT_KEY);
        $toBeSignData = implode('', $toBeSignData);
        //数据签名
        $sign = md5($toBeSignData);
        //二维码数据
        return base64_encode($cur_timestamp . '|' . $verify_code . '|' . $sign);
    }

    //base64切分成核销码；
    protected function decryptOrderData($orderData)
    {
        $std        = new stdClass();
        $std->error = 0;
        $orderData = base64_decode($orderData);
        if (empty($orderData)) {
            $std->error = -1;

            return $std;
        }
        $orderData = explode('|', $orderData);
        if (count($orderData) != 3) {
            $std->error = -2;

            return $std;
        }
        $originSign   = $orderData[2];
        $orderData[2] = ORDER_ENCRYPT_KEY;

        $sign = md5(implode('', $orderData));

        if ($sign != $originSign) {
            $std->error = -3;

            return $std;
        }
        $std->orderId = $orderData[1];
        return $std;
    }


    protected function getSmsSignByGtype($gtype)
    {
        $sign = null;
        switch ($gtype) {
            case CONTEST_GTYPE_DEFAULT:
                $sign = SMS_SIGN_LEWUHUI;
                break;
            case CONTEST_GTYPE_BUSINESS:
                $sign = SMS_SIGN_INNOSPACE;
                break;
            case CONTEST_GTYPE_INVESTOR:
                $sign = SMS_SIGN_INNO_ANGEL;
                break;
        }

        return $sign;
    }

    protected function ajax_search_tag()
    {
        $tag_type = $this->get_check('tag_type', PARAM_NOT_NULL_NOT_EMPTY, PARAM_TYPE_INT);
        $name     = $this->get_check('name', PARAM_NOT_NULL_NOT_EMPTY);

        $result = $this->tag->searchTag($tag_type, $name);

        $this->display($result);
    }

    protected function ajax_add_tag()
    {
        $tag_type = $this->post_check('tag_type', PARAM_NOT_NULL_NOT_EMPTY, PARAM_TYPE_INT);
        $tag_name = $this->post_check('tag_name', PARAM_NOT_NULL_NOT_EMPTY);

        $result = $this->tag->addTag($tag_type, $tag_name);
        if (empty($result->data)) {
            $result->error = -1;
        }

        return $this->display($result);
    }
}
