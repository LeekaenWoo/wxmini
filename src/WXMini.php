<?php

namespace Leekaen\WXMini;

class WXMini
{
    private $appId           = '';
    private $appSecret       = '';
    private $code2SessionUrl = '';

    public function __construct()
    {
        $this->appId           = config('wxmini.app_id');
        $this->appSecret       = config('wxmini.app_secret');
        $this->code2SessionUrl = config('wxmini.code2session_url');
    }

    /**
     * 全部正常返回
     {
        "openid": "OPENID",
        "session_key": "SESSIONKEY",
        "unionid": "UNIONID"//可能没有
    }
     */
    public function code2Session($code)
    {
        $requestUrl = sprintf($this->code2SessionUrl, $this->appId, $this->appSecret, $code);
        $result = static::sendRequest($this->code2SessionUrl);

        if ($result['ret']) {
            $info = json_decode($result['msg'], true);
        } else {
            $info = ['errcode' => 'curl error: ' . $resul['errno'], 'errmsg' => $result['msg']];
        }

        return $info;
    }

    /**
     * 根据小程序传递的加密后数据，解密为明文数据
     * @returan string errCode | array userInfo
     */
    public function getUserInfo($encryptedData, $iv, $sessionKey)
    {
        $data = [];

        $cryptor = new WXBizDataCrypt($this->appId, $sessionKey);

        $errCode = $cryptor->decryptData($encryptedData, $iv, $data);

        if ($errCode == 0) {
            return $data;
        } else {
            return $errCode;
        }
    }

    /**
     * CURL发送Request请求,含POST和REQUEST
     * @param string $url 请求的链接
     * @param mixed $params 传递的参数
     * @param string $method 请求的方法
     * @param mixed $options CURL的参数
     * @return array
     */
    public static function sendRequest($url, $params = [], $method = 'POST', $options = [])
    {
        $method       = strtoupper($method);
        $protocol     = substr($url, 0, 5);
        $query_string = is_array($params) ? http_build_query($params) : $params;

        $ch       = curl_init();
        $defaults = [];
        if ('GET' == $method) {
            $geturl                = $query_string ? $url . (stripos($url, "?") !== false ? "&" : "?") . $query_string : $url;
            $defaults[CURLOPT_URL] = $geturl;
        } else {
            $defaults[CURLOPT_URL] = $url;
            if ($method == 'POST') {
                $defaults[CURLOPT_POST] = 1;
            } else {
                $defaults[CURLOPT_CUSTOMREQUEST] = $method;
            }
            $defaults[CURLOPT_POSTFIELDS] = $query_string;
        }

        $defaults[CURLOPT_HEADER]         = false;
        $defaults[CURLOPT_USERAGENT]      = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.98 Safari/537.36";
        $defaults[CURLOPT_FOLLOWLOCATION] = true;
        $defaults[CURLOPT_RETURNTRANSFER] = true;
        $defaults[CURLOPT_CONNECTTIMEOUT] = 3;
        $defaults[CURLOPT_TIMEOUT]        = 3;

        // disable 100-continue
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));

        if ('https' == $protocol) {
            $defaults[CURLOPT_SSL_VERIFYPEER] = false;
            $defaults[CURLOPT_SSL_VERIFYHOST] = false;
        }

        curl_setopt_array($ch, (array) $options + $defaults);

        $ret = curl_exec($ch);
        $err = curl_error($ch);

        if (false === $ret || !empty($err)) {
            $errno = curl_errno($ch);
            $info  = curl_getinfo($ch);
            curl_close($ch);
            return [
                'ret'   => false,
                'errno' => $errno,
                'msg'   => $err,
                'info'  => $info,
            ];
        }
        curl_close($ch);
        return [
            'ret' => true,
            'msg' => $ret,
        ];
    }
}
