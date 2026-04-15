<?php

namespace App\Services;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

class SmsService
{
    /**
     * 发送短信验证码
     */
    public function sendVerificationCode(string $phone, string $code): array
    {
        $driver = config('sms.driver');
        
        if ($driver === 'mock') {
            // 模拟发送，直接返回成功（开发测试用）
            return [
                'success' => true,
                'message' => '模拟发送成功',
                'code' => $code,
            ];
        }
        
        if ($driver === 'aliyun') {
            return $this->sendAliyunSms($phone, $code);
        }
        
        return [
            'success' => false,
            'message' => '未知的短信服务商',
        ];
    }
    
    /**
     * 阿里云短信发送
     */
    private function sendAliyunSms(string $phone, string $code): array
    {
        try {
            AlibabaCloud::accessKeyClient(
                config('sms.aliyun.access_key_id'),
                config('sms.aliyun.access_key_secret')
            )
                ->regionId('cn-hangzhou')
                ->asDefaultClient();
            
            $result = AlibabaCloud::rpc()
                ->product('Dysmsapi')
                ->version('2017-05-25')
                ->action('SendSms')
                ->method('POST')
                ->host('dysmsapi.aliyuncs.com')
                ->options([
                    'query' => [
                        'PhoneNumbers' => $phone,
                        'SignName' => config('sms.aliyun.sign_name'),
                        'TemplateCode' => config('sms.aliyun.template_code'),
                        'TemplateParam' => json_encode(['code' => $code]),
                    ],
                ])
                ->request();
            
            $result = $result->toArray();
            
            if ($result['Code'] === 'OK') {
                return [
                    'success' => true,
                    'message' => '发送成功',
                ];
            }
            
            return [
                'success' => false,
                'message' => $result['Message'] ?? '发送失败',
            ];
            
        } catch (ClientException $e) {
            return [
                'success' => false,
                'message' => '短信发送失败：' . $e->getErrorMessage(),
            ];
        } catch (ServerException $e) {
            return [
                'success' => false,
                'message' => '短信发送失败：' . $e->getErrorMessage(),
            ];
        }
    }
}