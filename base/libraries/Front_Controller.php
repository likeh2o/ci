<?php defined('BASEPATH') or exit('No direct script access allowed');

require_once(dirname(__FILE__, 2) . '/config/Base_Error_Code.php');

/**
 * Class Front_Controller
 */
class Front_Controller extends CI_Controller
{
    private $smarty_tpl;

    public function __construct()
    {
        parent::__construct();
        if (is_cli()) {
            exit('error : is front controller!');
        }
        $this->load->add_package_path(dirname(BASEPATH));
        $this->load->library('front_smarty', '','smarty');
        $this->load->library('session');
        $this->load->helper('demo');
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

    protected function getSmartyDefaultTplPath()
    {
        return $this->smarty->template_dir[0] . $this->router->directory . $this->router->class;
    }

    protected function setSmartyTpl($tpl)
    {
        $this->smarty_tpl = $tpl;
    }

    protected function displayError($info, $code = -1)
    {
        log_message('error', $info);
        exit(json_encode(array('error' => $code, 'info' => $info)));
    }

    protected function display($data = array())
    {
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

        $this->smarty->display($tpl);
        exit;
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

    protected function isWeixinClient()
    {
        $userAgent = addslashes($_SERVER['HTTP_USER_AGENT']);
        if (strpos($userAgent, 'MicroMessenger') === false && strpos($userAgent, 'Windows Phone') === false) {
            return false;
        }

        return true;
    }

    private function getCaptcha()
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

    protected function captcha()
    {
        return $this->getCaptcha();
    }

}
