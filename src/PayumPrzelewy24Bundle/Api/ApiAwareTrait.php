<?php

namespace pelsedu\PayumPrzelewy24Bundle\Api;

use Payum\Core\Exception\UnsupportedApiException;

trait ApiAwareTrait
{
    protected ApiClient $api;

    public function setApi($api): void
    {
        if ($api instanceof ApiClient) {
            $this->api = $api;
            return;
        }

        throw new UnsupportedApiException();
    }
}
