<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\StripePayment;
use AppBundle\Entity\Task;
use AppBundle\Form\DeliveryEmbedType;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderInterface;
use Cocur\Slugify\SlugifyInterface;
use FOS\UserBundle\Util\UserManipulator;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class EmbedController extends Controller
{
    use DeliveryTrait;

    protected function getDeliveryRoutes()
    {
        return [];
    }

    private function createDeliveryForm()
    {
        $delivery = Delivery::create();

        return $this->get('form.factory')->createNamed('delivery', DeliveryEmbedType::class, $delivery);
    }

    private function getPricingRuleSet()
    {
        $pricingRuleSet = null;
        try {
            $pricingRuleSetId = $this->get('craue_config')->get('embed.delivery.pricingRuleSet');
            if ($pricingRuleSetId) {
                $pricingRuleSet = $this->getDoctrine()
                    ->getRepository(PricingRuleSet::class)
                    ->find($pricingRuleSetId);
            }
        } catch (\RuntimeException $e) {}

        return $pricingRuleSet;
    }

    private function findOrCreateUser($email, $telephone, SlugifyInterface $slugify, UserManipulator $userManipulator)
    {
        $userManager = $this->get('fos_user.user_manager');

        $user = $userManager->findUserByEmail($email);
        if (!$user) {

            [ $localPart, $domain ] = explode('@', $email);
            $username = $slugify->slugify($localPart, ['separator' => '_']);
            $password = random_bytes(16);

            $user = $userManipulator->create($username, $password, $email, true, false);
        }

        if (null === $user->getTelephone() || !$user->getTelephone()->equals($telephone)) {
            $user->setTelephone($telephone);
            $userManager->updateUser($user);
        }

        return $user;
    }

    /**
     * @Route("/embed/delivery/start", name="embed_delivery_start")
     * @Template
     */
    public function deliveryStartAction(Request $request)
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $pricingRuleSet = $this->getPricingRuleSet();
        if (!$pricingRuleSet) {
            throw new NotFoundHttpException('Pricing rule set not configured');
        }

        $form = $this->createDeliveryForm();
        $form->handleRequest($request);

        return $this->render('@App/embed/delivery/start.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/embed/delivery/summary", name="embed_delivery_summary")
     * @Template
     */
    public function deliverySummaryAction(Request $request, DeliveryManager $deliveryManager, SettingsManager $settingsManager)
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $pricingRuleSet = $this->getPricingRuleSet();
        if (!$pricingRuleSet) {
            throw new NotFoundHttpException('Pricing rule set not configured');
        }

        $form = $this->createDeliveryForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            try {

                $delivery = $form->getData();
                $price = $this->getDeliveryPrice($delivery, $pricingRuleSet, $deliveryManager);

                $rate = $this->get('sylius.tax_rate_resolver')->resolve(
                    $this->get('sylius.factory.product_variant')->createForDelivery($delivery, $price)
                );

                $priceExcludingTax = (int) round($price / (1 + $rate->getAmount()));

                return $this->render('@App/embed/delivery/summary.html.twig', [
                    'price' => $price,
                    'price_excluding_tax' => $priceExcludingTax,
                    'form' => $form->createView(),
                ]);

            } catch (\Exception $e) {
                $form->addError(new FormError($e->getMessage()));
            }

        }

        return $this->render('@App/embed/delivery/start.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/embed/delivery/process", name="embed_delivery_process")
     * @Template
     */
    public function deliveryProcessAction(
        Request $request,
        SlugifyInterface $slugify,
        OrderManager $orderManager,
        DeliveryManager $deliveryManager,
        UserManipulator $userManipulator)
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $pricingRuleSet = $this->getPricingRuleSet();
        if (!$pricingRuleSet) {
            throw new NotFoundHttpException('Pricing rule set not configured');
        }

        $form = $this->createDeliveryForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $delivery = $form->getData();

            $email = $form->get('email')->getData();
            $telephone = $form->get('telephone')->getData();

            $user  = $this->findOrCreateUser($email, $telephone, $slugify, $userManipulator);
            $price = $this->getDeliveryPrice($delivery, $pricingRuleSet, $deliveryManager);
            $order = $this->createOrderForDelivery($delivery, $price, $user);

            $billingAddress = $form->get('billingAddress')->getData();
            $this->setBillingAddress($order, $billingAddress);

            $name = $form->get('name')->getData();
            $order->setNotes($name);

            $this->get('sylius.repository.order')->add($order);
            $orderManager->onDemand($order);
            $this->get('sylius.manager.order')->flush();

            $this->addFlash(
                'embed_delivery',
                $this->get('translator')->trans('embed.delivery.confirm_message')
            );

            return $this->redirectToRoute('public_order', ['number' => $order->getNumber()]);
        }

        return $this->render('@App/embed/delivery/summary.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function setBillingAddress(OrderInterface $order, Address $address)
    {
        if (null !== $address->getFirstName()
        ||  null !== $address->getLastName()
        ||  null !== $address->getCompany()
        ||  null !== $address->getStreetAddress()) {
            $order->setBillingAddress($address);
        }
    }
}
