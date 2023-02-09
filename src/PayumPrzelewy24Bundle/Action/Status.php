<?php

namespace arteneo\PayumPrzelewy24Bundle\Action;

use arteneo\PayumPrzelewy24Bundle\Api\ApiClient;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\Request\GetHumanStatus;

class Status implements ActionInterface
{
    /**
     * @param mixed $request
     *
     * @throws \Payum\Core\Exception\RequestNotSupportedException if the action dose not support the request
     */
    public function execute($request)
    {
        /** @var PaymentInterface $payment */
        $payment = $request->getFirstModel();

        $details = ArrayObject::ensureArrayObject($payment->getDetails());

        if (ApiClient::STATUS_SUCCESS == $details['state']) {
            $request->markCaptured();

            return;
        }

        $request->markUnknown();
    }

    /**
     * @param mixed $request
     *
     * @return bool
     */
    public function supports($request)
    {
        return $request instanceof GetHumanStatus
            && $request->getModel() instanceof ArrayObject
            && $request->getFirstModel() instanceof PaymentInterface;
    }
}
