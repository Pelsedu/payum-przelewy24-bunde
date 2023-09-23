<?php

namespace arteneo\PayumPrzelewy24Bundle\Api;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\Security\TokenInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ApiClient
{
    public const STATUS_SUCCESS = 'success';

    /** @var ClientInterface */
    private $httpClient;

    /**
     * @param array $parameters
     */
    public function __construct(ClientInterface $httpClient, $parameters = [])
    {
        $this->httpClient = $httpClient;
        $this->parameters = $parameters;
    }

    public function getRegisterTransactionUrl()
    {
        return $this->parameters['sandbox'] ?
            'https://sandbox.przelewy24.pl/api/v1/transaction/register' :
            'https://secure.przelewy24.pl/api/v1/transaction/register';
    }

    public function getStatusPaymentUrl()
    {
        return $this->parameters['sandbox'] ?
            'https://sandbox.przelewy24.pl/api/v1/transaction/verify' :
            'https://secure.przelewy24.pl/api/v1/transaction/verify';
    }

    public function getTransactionRedirect(string $token)
    {
        return $this->parameters['sandbox'] ?
            'https://sandbox.przelewy24.pl/trnRequest/' . $token :
            'https://secure.przelewy24.pl/trnRequest/' . $token;
    }

    public function getBasicAuth()
    {
        return [$this->parameters['clientId'], $this->parameters['clientSecret']];
    }

    public function getReturnUrl(PaymentInterface $payment, TokenInterface $token)
    {
        $details = $payment->getDetails();
        if (isset($details['returnAbsoluteUrl'])) {
            return $details['returnAbsoluteUrl'];
        }

        if (isset($details['returnUrl'])) {
            return $this->parameters['serviceDomain'] . $details['returnUrl'];
        }

        if (isset($details['returnRoute']) && isset($this->parameters['router'])) {
            return $this->parameters['router']->generate($details['returnRoute'], ['payum_token' => $token->getHash()], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $this->parameters['serviceDomain'] . '/';
    }

    public function getStatusUrl(PaymentInterface $payment, TokenInterface $token)
    {
        if (isset($this->parameters['router'])) {
            return $this->parameters['serviceDomain'] . $this->parameters['router']->generate('payum_capture_do', ['payum_token' => $token->getHash()]);
        }

        return sprintf('%s/payment/capture/%s', $this->parameters['serviceDomain'], $token->getHash());
    }

    public function registerTransaction(PaymentInterface $payment, TokenInterface $token)
    {
        $response = $this->httpClient->request('POST', $this->getRegisterTransactionUrl(), [
            'json' => $this->buildFormParamsForPostRequest($payment, $token),
            'auth' => $this->getBasicAuth(),
        ]);

        $responseArray = json_decode($response->getBody()->getContents(), true);

        if (isset($responseArray['data']) && isset($responseArray['data']['token'])) {
            return $responseArray['data']['token'];
        }

        throw new \RuntimeException('Token not received from Przelewy24');
    }

    public function buildFormParamsForPostRequest(PaymentInterface $payment, TokenInterface $token)
    {
        $details = $payment->getDetails();

        $params = [
            'sessionId' => $details['sessionId'],
            'description' => $details['description'],
            'merchantId' => $this->parameters['clientId'],
            'posId' => $this->parameters['clientId'],
            'country' => $this->parameters['country'],
            'language' => $this->parameters['language'],
            'amount' => $details['amount'],
            'currency' => $details['currency'],
            'email' => $details['email'],
            'urlReturn' => $this->getReturnUrl($payment, $token),
            'urlStatus' => $this->getStatusUrl($payment, $token),
            'sign' => $this->createHashForRegisterTransaction($details),
        ];

        return $params;
    }

    public function verifyPaymentNotification(ArrayObject $notificationResponse)
    {
        $requiredNotifications = [
            'merchantId',
            'posId',
            'sessionId',
            'amount',
            'originAmount',
            'currency',
            'orderId',
            'methodId',
            'statement',
            'sign',
        ];

        foreach ($requiredNotifications as $property) {
            if (!isset($notificationResponse[$property])) {
                return false;
            }
        }

        if ($this->createHashForNotification($notificationResponse->toUnsafeArray()) !== $notificationResponse['sign']) {
            return false;
        }

        return true;
    }

    public function handlePaymentNotification(ArrayObject $notificationResponse)
    {
        if (!$this->verifyPaymentNotification($notificationResponse)) {
            throw new \InvalidArgumentException('Response verification failed.');
        }

        $json = [
            'merchantId' => $this->parameters['clientId'],
            'posId' => $this->parameters['clientId'],
            'sessionId' => $notificationResponse['sessionId'],
            'amount' => $notificationResponse['amount'],
            'currency' => $notificationResponse['currency'],
            'orderId' => $notificationResponse['orderId'],
            'sign' => $this->createHashForPaymentStatus($notificationResponse->toUnsafeArray()),
        ];

        try {
            $response = $this->httpClient->request('PUT', $this->getStatusPaymentUrl(), [
                'json' => $json,
                'auth' => $this->getBasicAuth(),
            ]);

            $responseArray = json_decode($response->getBody()->getContents(), true);

            if (isset($responseArray['data']) && isset($responseArray['data']['status'])) {
                return $responseArray['data']['status'];
            }

            throw new \RuntimeException('Transaction verification response from Przelewy24 is invalid');
        } catch (ClientException $requestException) {
            throw new \RuntimeException($requestException->getMessage());
        }
    }

    private function createHashForNotification(array $details)
    {
        $hashString = '{"merchantId":' . $details['merchantId'] . ',"posId":' . $details['posId'] . ',"sessionId":"' . $details['sessionId'] . '","amount":' . $details['amount'] . ',"originAmount":' . $details['originAmount'] . ',"currency":"' . $details['currency'] . '","orderId":' . $details['orderId'] . ',"methodId":' . $details['methodId'] . ',"statement":"' . $details['statement'] . '","crc":"' . $this->parameters['crc'] . '"}';

        return hash('sha384', $hashString);
    }

    private function createHashForPaymentStatus(array $details)
    {
        $hashString = '{"sessionId":"' . $details['sessionId'] . '","orderId":' . $details['orderId'] . ',"amount":' . $details['amount'] . ',"currency":"' . $details['currency'] . '","crc":"' . $this->parameters['crc'] . '"}';

        return hash('sha384', $hashString);
    }

    private function createHashForRegisterTransaction(array $details)
    {
        $hashString = '{"sessionId":"' . $details['sessionId'] . '","merchantId":' . $this->parameters['clientId'] . ',"amount":' . $details['amount'] . ',"currency":"' . $details['currency'] . '","crc":"' . $this->parameters['crc'] . '"}';

        return hash('sha384', $hashString);
    }
}
