<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\StripePayment;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Form\OrdersExportType;
use AppBundle\Service\OrderManager;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;

trait OrderTrait
{
    abstract protected function getOrderList(Request $request);

    private function orderAsJson(Order $order)
    {
        $orderNormalized = $this->get('serializer')->normalize($order, 'jsonld', [
            'resource_class' => Order::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['order', 'place']
        ]);

        return new JsonResponse($orderNormalized, 200);
    }

    public function orderListAction(Request $request)
    {
        $signer = new Sha256();

        $stdClass = new \stdClass();

        $token = (new Builder())
            // ->setIssuer('http://example.com') // Configures the issuer (iss claim)
            // ->setAudience('http://example.org') // Configures the audience (aud claim)
            // ->setId('4f1g23a12aa', true) // Configures the id (jti claim), replicating as a header item
            ->setIssuedAt(time()) // Configures the time that the token was issue (iat claim)
            // ->setNotBefore(time() + 60) // Configures the time that the token can be used (nbf claim)
            ->setExpiration(time() + 3600) // Configures the expiration time of the token (exp claim)
            // ->set('uid', 1) // Configures a new claim, called "uid"
            ->set('resource', ['question' => 1])
            ->set('params', $stdClass)
            ->sign($signer, 'c63d062fcb6e537fe5ddbaa83f80d34af3fdac46c69622fea6144db7b7b0dfcc') // creates a signature using "testing" as key
            ->getToken(); // Retrieves the generated token

        // var_dump((string) $token);

        $iframeUrl = sprintf('http://localhost:3001/embed/question/%s#bordered=true&titled=true', $token);

        // var_dump((string) $iframeUrl);

        $response = new Response();

        $showCanceled = false;
        if ($request->query->has('show_canceled')) {
            $showCanceled = $request->query->getBoolean('show_canceled');
            $response->headers->setCookie(new Cookie('__show_canceled', $showCanceled ? 'on' : 'off'));
        } elseif ($request->cookies->has('__show_canceled')) {
            $showCanceled = $request->cookies->getBoolean('__show_canceled');
        }

        $exportForm = $this->createForm(OrdersExportType::class);

        $authorizationChecker = $this->get('security.authorization_checker');
        if ($authorizationChecker->isGranted('ROLE_ADMIN')) {

            $exportForm->handleRequest($request);
            if ($exportForm->isSubmitted() && $exportForm->isValid()) {
                $data = $exportForm->getData();

                $start = $exportForm->get('start')->getData();
                $end = $exportForm->get('end')->getData();

                $filename = sprintf('orders-%s-%s.csv', $start->format('Y-m-d'), $end->format('Y-m-d'));

                $response = new Response($data['csv']);
                $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    $filename
                ));

                return $response;
            }
        }

        $routes = $request->attributes->get('routes');

        [ $orders, $pages, $page ] = $this->getOrderList($request);

        return $this->render($request->attributes->get('template'), [
            'orders' => $orders,
            'pages' => $pages,
            'page' => $page,
            'routes' => $request->attributes->get('routes'),
            'show_canceled' => $showCanceled,
            'export_form' => $exportForm->createView(),
            'dashboard_iframe_url' => $iframeUrl,
        ], $response);
    }

    public function orderInvoiceAction($number, Request $request)
    {
        $order = $this->get('sylius.repository.order')->findOneBy([
            'number'=> $number
        ]);

        $this->accessControl($order);

        $html = $this->renderView('@App/order/invoice.html.twig', [
            'order' => $order
        ]);

        return new Response($this->get('knp_snappy.pdf')->getOutputFromHtml($html), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function acceptOrderAction($id, Request $request, OrderManager $orderManager)
    {
        $order = $this->get('sylius.repository.order')->find($id);

        $this->accessControl($order->getRestaurant());

        try {
            $orderManager->accept($order);
            $this->get('sylius.manager.order')->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    public function refuseOrderAction($id, Request $request, OrderManager $orderManager)
    {
        $order = $this->get('sylius.repository.order')->find($id);

        $this->accessControl($order->getRestaurant());

        try {
            $orderManager->refuse($order);
            $this->get('sylius.manager.order')->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    public function delayOrderAction($id, Request $request, OrderManager $orderManager)
    {
        $order = $this->get('sylius.repository.order')->find($id);

        $this->accessControl($order->getRestaurant());

        try {
            $orderManager->delay($order);
            $this->get('sylius.manager.order')->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    private function cancelOrderById($id, OrderManager $orderManager)
    {
        $order = $this->get('sylius.repository.order')->find($id);
        $this->accessControl($order->getRestaurant());

        $orderManager->cancel($order);
        $this->get('sylius.manager.order')->flush();

        return $order;
    }

    public function cancelOrderAction($id, Request $request, OrderManager $orderManager)
    {
        $order = $this->cancelOrderById($id, $orderManager);

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }
}
