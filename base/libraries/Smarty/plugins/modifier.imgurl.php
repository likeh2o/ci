<?php
/**
 * Smarty plugin
 *
 * @package    Smarty
 * @subpackage PluginsModifier
 */
/**
 * Smarty staticurl modifier plugin
 *
 * Type:     modifier<br>
 * Name:     imgurl <br>
 * Purpose:  return an empty string
 *
 * @author   xumenghe@gmail.com
 *
 * @param array $params parameters
 *
 * @return string
 *
 * Examples : {"<appname>/<modulename>/[img]/banner.jpg"|imgurl}
 * {$object->fileid|imgurl}
 */

if (!defined('_STATIC_RES_CDN_DOMAIN_')) {
    define('_STATIC_RES_CDN_DOMAIN_', 'static.innospace.cn');
}

if (!defined('_UPLOAD_RES_CDN_DOMAIN_')) {
    define('_UPLOAD_RES_CDN_DOMAIN_', 'res.innospace.cn');
}

if (!defined('RELEASE_VERSION')) {
    define('RELEASE_VERSION', '0.0.1');
}


// // 图片处理指令，按固定宽度200px，高度自适应，等比例缩放。
// if (!defined('CMD_200W_WEBP')) {
//     define('CMD_200W_WEBP', 'image/resize,m_fill,w_200,limit_1/auto-orient,0/sharpen,100/format,webp');
// }
// // 图片处理指令，按固定宽度300px，高度自适应，等比例缩放。
// if (!defined('CMD_300W_WEBP')) {
//     define('CMD_300W_WEBP', 'image/resize,m_lfit,w_300,limit_1/auto-orient,0/sharpen,100/format,webp');
// }
// // 图片处理指令，按固定高度300px，宽度自适应，等比例缩放。
// if (!defined('CMD_300H_WEBP')) {
//     define('CMD_300H_WEBP', 'image/resize,m_lfit,h_300,limit_1/auto-orient,0/format,webp');
// }
// // 图片处理指令，宽高200px，按短边缩放，居中裁剪。
// if (!defined('CMD_200WH_RESIZE_WEBP')) {
//     define('CMD_200WH_RESIZE_WEBP', 'image/resize,m_fill,w_200,h_200,limit_0/auto-orient,0/format,webp');
// }
// // 图片处理指令，限定宽高400px，按短边等比例缩放.
// if (!defined('CMD_400WH_MFIT_WEBP')) {
//     define('CMD_400WH_MFIT_WEBP', 'image/resize,m_lfit,w_400,h_400,limit_0/auto-orient,0/sharpen,100/format,webp');
// }


function smarty_modifier_imgurl($string, $cmd = '200w')
{
    $info = pathinfo($string);

    if (empty($info['extension']) and strlen($string) == 32) {
        // 本站上传的图片
        return 'http://' . _UPLOAD_RES_CDN_DOMAIN_ . '/' . $string . '?' . ALIYUN_OSS_IMAURL_PARAM_NAME . '=' . smarty_modifier_imgurl_cmd($cmd);
    }

    if (!empty($info['extension'])) {
        // 纳入版本控制的图片
        return 'http://' . _STATIC_RES_CDN_DOMAIN_ . '/img/' . $string . '?v=' . RELEASE_VERSION;
    }

    // 其他图片
    return $string;
}

function smarty_modifier_imgurl_cmd($cmd)
{
    global $IMAGE_SIZE_LIST;

    return !empty($IMAGE_SIZE_LIST[$cmd]) ? $IMAGE_SIZE_LIST[$cmd] : '';
}
