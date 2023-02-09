<?php

namespace arteneo\PayumPrzelewy24Bundle\Factory;

use arteneo\PayumPrzelewy24Bundle\Api\ApiClient;
use GuzzleHttp\Client;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

class Przelewy24OffsiteGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config)
    {
        if (false == $config['payum.api']) {
            $config['payum.default_options'] = [
                'clientId' => null,
                'clientSecret' => null,
                'crc' => null,
                'router' => null,
                'sandbox' => true,
                'country' => 'PL',
                'language' => 'pl',
            ];

            $config['payum.http_client'] = new Client();

            $config->defaults($config['payum.default_options']);
            $config['payum.required_options'] = ['clientId', 'clientSecret', 'serviceDomain', 'crc'];

            $config['httplug.client'] = new Client();

            $config['payum.api'] = function (ArrayObject $config) {
                $config->validateNotEmpty($config['payum.required_options']);

                return new ApiClient(
                    $config['payum.http_client'],
                    [
                        'clientId' => $config['clientId'],
                        'clientSecret' => $config['clientSecret'],
                        'crc' => $config['crc'],
                        'serviceDomain' => $config['serviceDomain'],
                        'router' => $config['router'],
                        'sandbox' => $config['sandbox'],
                        'country' => $config['country'],
                        'language' => $config['language'],
                    ]
                );
            };
        }
    }
}
