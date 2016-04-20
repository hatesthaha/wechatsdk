<?php

namespace wuwenhan\src\core;

use Yii;
use Yii\base\InvalidParamException;

class Http
{
    /**
     * 微信接口基本地址
     */
    const WECHAT_BASE_URL = 'https://api.weixin.qq.com';
    /**
     * @var array 最后请求的错误信息
     */
    public $lastErrorInfo;

    /**
     * Get方式调用微信接口
     * @param $url
     * @param null $params
     * @return array
     */
    public function httpGet($url, $params = null)
    {
        return $this->parseHttpResult($url, $params, 'get');
    }

    /**
     * Post方式调用微信接口
     * @param $url
     * @param null $params
     * @return array
     */
    public function httpPost($url, $params = null)
    {
        return $this->parseHttpResult($url, $params, 'post');
    }

    /**
     * 解析api回调请求
     * 会根据返回结果处理响应的回调结果.如 40001 access_token失效(会强制更新access_token后)重发, 保证请求的的有效
     * @param $url
     * @param $params
     * @param $method
     * @param bool $force 是否强制更新access_token 并再次请求
     * @return bool|mixed
     */
    protected function parseHttpResult($url, $params, $method, $force = true)
    {
        if (stripos($url, 'http://') === false && stripos($url, 'https://') === false) {
            $url = self::WECHAT_BASE_URL . $url;
        }
        $return = $this->http($url, $params, $method);
        $return = json_decode($return, true) ? : $return;
        if (isset($return['errcode']) && $return['errcode']) {
            $this->lastErrorInfo = $return;
            $log = [
                'class' => __METHOD__,
                'arguments' => func_get_args(),
                'result' => $return,
            ];
            switch ($return['errcode']) {
                case 40001: //access_token 失效,强制更新access_token, 并更新地址重新执行请求
                    if ($force) {
                        Yii::warning($log, 'wechat.sdk');
                        $url = preg_replace_callback("/access_token=([^&]*)/i", function(){
                            return 'access_token=' . (new AccessToken())->getAccessToken(true);
                        }, $url);
                        $return = $this->parseHttpResult($url, $params, $method, false); // 仅重新获取一次,否则容易死循环
                    }
                    break;
            }
            Yii::error($log, 'wechat.sdk');
        }
        return $return;
    }
    
    
    /**
     * Http协议调用微信接口方法
     * @param $url api地址
     * @param $params 参数
     * @param string $type 提交类型
     * @return bool|mixed
     * @throws \yii\base\InvalidParamException
     */
    protected function http($url, $params = null, $type = 'get')
    {
        $curl = curl_init();
        switch ($type) {
            case 'get':
                is_array($params) && $params = http_build_query($params);
                !empty($params) && $url .= (stripos($url, '?') === false ? '?' : '&') . $params;
                break;
            case 'post':
                curl_setopt($curl, CURLOPT_POST, true);
                if (!is_array($params)) {
                    throw new InvalidParamException("Post data must be an array.");
                }
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                break;
            case 'raw':
                curl_setopt($curl, CURLOPT_POST, true);
                if (is_array($params)) {
                    throw new InvalidParamException("Post raw data must not be an array.");
                }
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                break;
            default:
                throw new InvalidParamException("Invalid http type '{$type}.' called.");
        }
        if (stripos($url, "https://") !== false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1); // 微信官方屏蔽了ssl2和ssl3, 启用更高级的ssl
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $content = curl_exec($curl);
        $status = curl_getinfo($curl);
        curl_close($curl);
        if (isset($status['http_code']) && intval($status['http_code']) == 200) {
            return $content;
        }
        return false;
    }
    
}