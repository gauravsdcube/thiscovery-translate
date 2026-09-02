<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\services;

use humhub\modules\thiscoveryTranslate\models\ModuleSettings;
use Yii;
use yii\httpclient\Client;
use yii\httpclient\CurlTransport;

/**
 * Minimal Amazon Translate client using instance-role (IMDSv2) or optional static keys.
 * Signs requests with AWS Signature Version 4 — no aws-sdk-php required.
 *
 * @see https://docs.aws.amazon.com/translate/latest/APIReference/API_TranslateText.html
 */
class AwsTranslateClient
{
    private ModuleSettings $settings;

    public function __construct(?ModuleSettings $settings = null)
    {
        $this->settings = $settings ?: ModuleSettings::loadSettings();
    }

    /**
     * @param 'text'|'html' $format
     */
    public function translateText(string $text, string $sourceAmazon, string $targetAmazon, string $format = 'text'): string
    {
        $text = trim($text);
        if ($text === '' || $sourceAmazon === $targetAmazon) {
            return $text;
        }

        $payload = [
            'Text' => $text,
            'SourceLanguageCode' => $sourceAmazon,
            'TargetLanguageCode' => $targetAmazon,
        ];
        if ($format === 'html') {
            $payload['Settings'] = ['Formality' => 'FORMAL'];
            // HTML content type helps preserve markup
            $payload['Text'] = $text;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new \RuntimeException('Could not encode Translate request.');
        }

        $region = $this->settings->awsRegion ?: 'eu-west-2';
        $host = 'translate.' . $region . '.amazonaws.com';
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $credentials = $this->resolveCredentials();

        $headers = [
            'content-type' => 'application/x-amz-json-1.1',
            'host' => $host,
            'x-amz-date' => $amzDate,
            'x-amz-target' => 'AWSShineFrontendService_20170701.TranslateText',
        ];
        if (!empty($credentials['token'])) {
            $headers['x-amz-security-token'] = $credentials['token'];
        }

        $canonicalHeaders = '';
        $signedHeadersList = [];
        ksort($headers);
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= $k . ':' . trim($v) . "\n";
            $signedHeadersList[] = $k;
        }
        $signedHeaders = implode(';', $signedHeadersList);
        $payloadHash = hash('sha256', $body);
        $canonicalRequest = "POST\n/\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$dateStamp}/{$region}/translate/aws4_request";
        $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        $signature = $this->sign($stringToSign, $credentials['secret'], $dateStamp, $region, 'translate');
        $authorization = "{$algorithm} Credential={$credentials['key']}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $requestHeaders = [
            'Content-Type' => 'application/x-amz-json-1.1',
            'X-Amz-Date' => $amzDate,
            'X-Amz-Target' => 'AWSShineFrontendService_20170701.TranslateText',
            'Authorization' => $authorization,
        ];
        if (!empty($credentials['token'])) {
            $requestHeaders['X-Amz-Security-Token'] = $credentials['token'];
        }

        $client = new Client(['transport' => CurlTransport::class]);
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl('https://' . $host . '/')
            ->setHeaders($requestHeaders)
            ->setContent($body)
            ->setOptions([CURLOPT_TIMEOUT => 20])
            ->send();

        if (!$response->getIsOk()) {
            throw new \RuntimeException('Amazon Translate HTTP ' . $response->getStatusCode() . ': ' . $response->getContent());
        }

        $data = json_decode((string)$response->getContent(), true);
        if (!is_array($data) || empty($data['TranslatedText'])) {
            throw new \RuntimeException('Amazon Translate returned an empty result.');
        }
        return (string)$data['TranslatedText'];
    }

    /**
     * @return array{key: string, secret: string, token: string}
     */
    private function resolveCredentials(): array
    {
        // IAM instance role only (no access keys in settings/DB).
        return $this->fromInstanceMetadata();
    }

    /**
     * @return array{key: string, secret: string, token: string}
     */
    private function fromInstanceMetadata(): array
    {
        $token = $this->imdsPutToken();
        $roleName = trim($this->imdsGet('/latest/meta-data/iam/security-credentials/', $token));
        if ($roleName === '') {
            throw new \RuntimeException('No IAM instance role found. Attach TranslateReadOnly to the EC2 role or set access keys.');
        }
        $json = $this->imdsGet('/latest/meta-data/iam/security-credentials/' . rawurlencode($roleName), $token);
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['AccessKeyId']) || empty($data['SecretAccessKey'])) {
            throw new \RuntimeException('Could not load IAM role credentials from instance metadata.');
        }
        return [
            'key' => (string)$data['AccessKeyId'],
            'secret' => (string)$data['SecretAccessKey'],
            'token' => (string)($data['Token'] ?? ''),
        ];
    }

    private function imdsPutToken(): string
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'PUT',
                'header' => "X-aws-ec2-metadata-token-ttl-seconds: 21600\r\n",
                'timeout' => 2,
            ],
        ]);
        $token = @file_get_contents('http://169.254.169.254/latest/api/token', false, $ctx);
        return is_string($token) ? $token : '';
    }

    private function imdsGet(string $path, string $token): string
    {
        $headers = "Accept: */*\r\n";
        if ($token !== '') {
            $headers .= 'X-aws-ec2-metadata-token: ' . $token . "\r\n";
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $headers,
                'timeout' => 2,
            ],
        ]);
        $body = @file_get_contents('http://169.254.169.254' . $path, false, $ctx);
        return is_string($body) ? $body : '';
    }

    private function sign(string $stringToSign, string $secret, string $dateStamp, string $region, string $service): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        return hash_hmac('sha256', $stringToSign, $kSigning);
    }
}
