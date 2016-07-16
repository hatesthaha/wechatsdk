<?php

namespace wuwenhan\wechatsdk\src\core;

use Yii;
use Yii\base\Event;
use Yii\web\HttpException;
use wuwenhan\wechatsdk\src\core\Http;



trait AccessToken
{

    /**
     * 缓存微信数据
     * @param $name
     * @param $value
     * @param null $duration
     * @return bool
     */
    protected function setCache($name, $value, $duration = null)
    {
        $duration === null && $duration = $this->cacheTime;
        return Yii::$app->cache->set($this->getCacheKey($name), $value, $duration);
    }
    /**
     * 获取缓存的键值
     * @param $name
     * @return string
     */
    protected function getCacheKey($name)
    {
        return implode('_', [$this->cachePrefix, $this->appId, $name]);
    }
    /**
     * 获取微信缓存数据
     * @param $name
     * @param null $defaultValue
     * @return mixed
     */
    protected function getCache($name, $defaultValue = null)
    {
        return Yii::$app->cache->get($this->getCacheKey($name), $defaultValue);
    }

    /**
     * 获取AccessToken
     * 会自动判断超时时间然后重新获取新的token
     * (会智能缓存accessToken)
     * @param bool $force 是否强制获取
     * @return string
     * @throws \yii\base\Exception
     */
    public function getAccessToken($force = false)
    {
        if ($this->_accessToken === null || $this->_accessToken['expire'] < YII_BEGIN_TIME || $force) {
            $result = !$force && $this->_accessToken === null ? $this->getCache('access_token', false) : false;
     
            if ($result === false) {
                if (!($result = $this->requestAccessToken())) {
                    throw new HttpException(500, 'Fail to get access_token from wechat server.');
                }
                $this->trigger(self::EVENT_AFTER_ACCESS_TOKEN_UPDATE, new Event(['data' => $result]));
                $this->setCache('access_token', $result, $result['expires_in']);
            }
            $this->setAccessToken($result);
        }
        return $this->_accessToken['token'];
    }
    /**
     * 设置AccessToken
     * @param string @param array $data  ['token' => 'token 字符串', 'expire' => 'token 超时时间']
     */
    public function setAccessToken(array $data)
    {
        if (!isset($data['access_token'])) {
            throw new InvalidParamException('The wechat access_token must be set.');
        } elseif(!isset($data['expire'])) {
            throw new InvalidParamException('Wechat access_token expire time must be set.');
        }
        $this->_accessToken = [
            'token' => $data['access_token'],
            'expire' => $data['expire']
        ];
    }
    /**
     * 请求服务器access_token
     * @return array
     */
    protected function requestAccessToken()
    {
      
        $result =(new Http())->httpGet(self::API_TOKEN_GET, [
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'grant_type' => $this->grant_type
        ]);
     
        if (isset($result['access_token'])) {
            $result['expire'] = $result['expires_in'] + (int)YII_BEGIN_TIME;
            return $result;
        }
        return false;
    }
}