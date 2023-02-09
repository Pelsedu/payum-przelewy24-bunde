<?php

namespace arteneo\PayumPrzelewy24Bundle\Action;

use arteneo\PayumPrzelewy24Bundle\Api\ApiAwareTrait;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\Request\GetHumanStatus;

class Notify implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;
    use ApiAwareTrait;

    /** @var ObjectRepository */
    private $objectRepository;
    /** @var ObjectManager */
    private $objectManager;

    public function __construct(ObjectRepository $objectRepository, ObjectManager $objectManager)
    {
        $this->objectRepository = $objectRepository;
        $this->objectManager = $objectManager;
    }

    /**
     * @param mixed $request
     *
     * @throws \Payum\Core\Exception\RequestNotSupportedException if the action dose not support the request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = $request->getModel();

        /** @var Payment $payment */
        $payment = $this->objectRepository->findOneBy(['number' => $model['sessionId']]);

        $model['amount'] = $payment->getTotalAmount();
        $model['currency'] = $payment->getCurrencyCode();

        if (!$this->api->verifyPaymentNotification($model)) {
            $this->updatePaymentStatus($payment, GetHumanStatus::STATUS_FAILED);

            return;
        }

        $state = $this->api->handlePaymentNotification($model);

        $details = array_merge($payment->getDetails(), ['state' => $state]);
        $payment->setDetails($details);

        $status = new GetHumanStatus($payment);
        $this->gateway->execute($status);

        $nextState = $status->getValue();

        $this->updatePaymentStatus($payment, $nextState);
    }

    private function updatePaymentStatus(PaymentInterface $payment, string $nextState)
    {
        $payment->setStatus($nextState);

        $this->objectManager->persist($payment);
        $this->objectManager->flush();
    }

    /**
     * @param mixed $request
     *
     * @return bool
     */
    public function supports($request)
    {
        return $request instanceof \Payum\Core\Request\Notify
            && $request->getModel() instanceof ArrayObject;
    }
}
