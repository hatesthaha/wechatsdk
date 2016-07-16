<?php
namespace wuwenhan\wechatsdk\src\core;

use Yii;
use Yii\base\Event;
use Yii\web\HttpException;
use wuwenhan\wechatsdk\src\core\Http;
use wuwenhan\wechatsdk\src\core\AccessToken;


trait JsApiTicket
{
    /**
     * 获取js api ticket
     * 超时后会自动重新获取JsApiTicket并触发self::EVENT_AFTER_JS_API_TICKET_UPDATE事件
     * @param bool $force 是否强制获取
     * @return mixed
     * @throws HttpException
     */
    public function getJsApiTicket($force = false)
    {
        $time = time(); // 为了更精确控制.取当前时间计算

        if ($this->_jsApiTicket === null || $this->_jsApiTicket['expire'] < $time || $force) {
            $result = $this->_jsApiTicket === null && !$force ? $this->getCache('js_api_ticket', false) : false;

            if ($result === false) {
               
                if (!($result = $this->requestJsApiTicket())) {
                    throw new HttpException(500, 'Fail to get jsapi_ticket from wechat server.');
                }
                $result['expire'] = $time + $result['expires_in'];
                $this->trigger(self::EVENT_AFTER_JS_API_TICKET_UPDATE, new Event(['data' => $result]));
                $this->setCache('js_api_ticket', $result, $result['expires_in']);
            }
            $this->setJsApiTicket($result);
        }
        return $this->_jsApiTicket['ticket'];
    }

    /**
     * 设置JsApiTicket
     * @param array $jsApiTicket
     */
    public function setJsApiTicket(array $jsApiTicket)
    {
        $this->_jsApiTicket = $jsApiTicket;
    }
    /**
     * 请求服务器jsapi_ticket
     * @param string $type
     * @return array|bool
     */
    protected function requestJsApiTicket($type = 'jsapi')
    {
        $http = new Http();
        $result = $http->httpGet(self::WECHAT_JS_API_TICKET_PREFIX, [
            'access_token' => $this->getAccessToken(),
            'type' => $type
        ]);
        return isset($result['ticket']) ? $result : false;
    }

    /**
     * 生成js 必需的config
     * 只需在视图文件输出JS代码:
     *  wx.config(<?= json_encode($wehcat->jsApiConfig()) ?>); // 默认全权限
     *  wx.config(<?= json_encode($wehcat->jsApiConfig([ // 只允许使用分享到朋友圈功能
     *      'jsApiList' => [
     *          'onMenuShareTimeline'
     *      ]
     *  ])) ?>);
     * @param array $config
     * @return array
     * @throws HttpException
     */
    public function jsApiConfig(array $config = [])
    {
        $data = [
            'jsapi_ticket' => $this->getJsApiTicket(),
            'noncestr' => Yii::$app->security->generateRandomString(16),
            'timestamp' => $_SERVER['REQUEST_TIME'],
            'url' => explode('#', Yii::$app->request->getAbsoluteUrl())[0]
        ];
        return array_merge([
            'debug' => YII_DEBUG,
            'appId' => $this->appId,
            'timestamp' => $data['timestamp'],
            'nonceStr' => $data['noncestr'],
            'signature' => sha1(urldecode(http_build_query($data))),
            'jsApiList' => [
                'checkJsApi',
                'onMenuShareTimeline',
                'onMenuShareAppMessage',
                'onMenuShareQQ',
                'onMenuShareWeibo',
                'hideMenuItems',
                'showMenuItems',
                'hideAllNonBaseMenuItem',
                'showAllNonBaseMenuItem',
                'translateVoice',
                'startRecord',
                'stopRecord',
                'onRecordEnd',
                'playVoice',
                'pauseVoice',
                'stopVoice',
                'uploadVoice',
                'downloadVoice',
                'chooseImage',
                'previewImage',
                'uploadImage',
                'downloadImage',
                'getNetworkType',
                'openLocation',
                'getLocation',
                'hideOptionMenu',
                'showOptionMenu',
                'closeWindow',
                'scanQRCode',
                'chooseWXPay',
                'openProductSpecificView',
                'addCard',
                'chooseCard',
                'openCard'
            ]
        ], $config);
    }

}