<?php

namespace App\Payments;

class MetePay_p {

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'appId' => [
                'label' => '应用Id',
                'description' => 'MetePay 应用Id',
                'type' => 'input',
            ],
            'secret' => [
                'label' => '应用密钥',
                'description' => 'MetePay 应用密钥',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order) {
        $params = array(
          'appId' => $this->config['appId'],
          'outTradeNo' => $order['trade_no'],
          'totalAmount' => number_format($order['total_amount'] / 100, 2, '.', '')
        );
        

        ksort($params);
        reset($params);
        $signStr = '';
        foreach ($params as $key => $value) {
            $signStr .= $key . '=' . urlencode($value) . '&';
        }
        $signStr = rtrim($signStr, '&');
        $md5Str = md5($signStr);
        $finalMd5Str = md5($md5Str . $this->config['secret']);
        $params['sign'] = $finalMd5Str;

	    $url = 'https://metelep.xyz/api/v1/order/pre/create';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'User-Agent: MetePay']);

        $response = curl_exec($ch);
        $result = json_decode($response);
        
        if (!$result->success) {
            abort(500, $result->msg);
        }

        curl_close($ch);

        if ($result->success) {
            return [
                'type' => 1,
                'data' => $result->data->cashierUrl
            ];
        } else {
            abort(500, '接口请求失败');
        }
    }

    public function notify($params)
    {
        $sign = $params['sign'];
        unset($params['sign']);
        ksort($params);
        reset($params);

        $signStr = '';
        foreach ($params as $key => $value) {
            $signStr .= $key . '=' . urlencode($value) . '&';
        }
        $signStr = rtrim($signStr, '&');
        $md5Str = md5($signStr);
        $finalMd5Str = md5($md5Str . $this->config['secret']);

        if ($sign !== $finalMd5Str) {
            return false;
        }

        $tradeStatus = $params['tradeStatus'];
        $outTradeNo = $params['outTradeNo'];
        $tradeNo = $params['tradeNo'];

        if ($tradeStatus === 'TRADE_SUCCESS') {
            return [
                'trade_no' => $outTradeNo,
                'callback_no' => $tradeNo
            ];
            http_response_code(200);
            die('success');
        } else {
            return false;
        }
    }
}