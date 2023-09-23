<?php

namespace pelsedu\PayumPrzelewy24Bundle\Controller;

use pelsedu\PayumPrzelewy24Bundle\Entity\Payment;
use FOS\RestBundle\Controller\Annotations\Route;
use Payum\Bundle\PayumBundle\Controller\PayumController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @TODO: Move it out, useless junk ;)
 * @Route("/payment")
 */
class PaymentController extends PayumController
{
    /**
     * @Route("/create", name="create_payment")
     */
    public function createPayment(Request $request): RedirectResponse
    {
        $storage = $this->get('payum')->getStorage(Payment::class);

        /** @var Payment $payment */
        $payment = $storage->create();
        $payment->setNumber(uniqid('', true));
        $payment->setCurrencyCode('PLN');
        $payment->setTotalAmount(100);
        $payment->setDescription('Description');
        $payment->setClientId($this->getUser()->getId());
        $payment->setClientEmail($this->getUser()->getEmail());

        $payment->setDetails([
            'sessionId' => $payment->getNumber(),
            'description' => $payment->getDescription(),
            'amount' => $payment->getTotalAmount(),
            'currency' => $payment->getCurrencyCode(),
            'email' => $payment->getClientEmail(),
        ]);

        $storage->update($payment);

        $captureToken = $this->get('payum')->getTokenFactory()->createCaptureToken(
            'przelewy24',
            $payment,
            'payment_done'
        );

        return $this->redirect($captureToken->getTargetUrl());
    }

    /**
     * @Route("/done", name="payment_done")
     *
     * @return Response
     * @throws \Exception
     */
    public function captureDoneAction(Request $request)
    {
        $token = $this->getPayum()->getHttpRequestVerifier()->verify($request);

        $identity = $token->getDetails();
        /** @var Payment $model */
        $model = $this->get('payum')->getStorage($identity->getClass())->find($identity);

        return new JsonResponse([
            'status' => $model->getStatus(),
        ]);
    }
}
