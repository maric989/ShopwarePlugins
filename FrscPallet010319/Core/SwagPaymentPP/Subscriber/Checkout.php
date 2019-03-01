<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagPaymentPaypalPlus\Subscriber;

use Enlight_Components_Session_Namespace as Session;
use Enlight_Controller_Action as Controller;
use Enlight_View_Default as View;
use Exception;
use Shopware\Components\Logger;
use Shopware\SwagPaymentPaypalPlus\Components\APIValidator;
use Shopware\SwagPaymentPaypalPlus\Components\LoggerService;
use Shopware\SwagPaymentPaypalPlus\Components\PaymentInstructionProvider;
use Shopware\SwagPaymentPaypalPlus\Components\RestClient;
use Shopware_Plugins_Frontend_SwagPaymentPaypal_Bootstrap as PaypalBootstrap;
use Shopware_Plugins_Frontend_SwagPaymentPaypalPlus_Bootstrap as Bootstrap;

/**
 * Class Checkout
 */
class Checkout
{
    /**
     * @var Bootstrap
     */
    protected $bootstrap;

    /**
     * @var PaypalBootstrap
     */
    protected $paypalBootstrap;

    /**
     * @var \Enlight_Config
     */
    protected $config;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var RestClient
     */
    protected $restClient;

    /**
     * @var Logger
     */
    private $pluginLogger;

    /**
     * @var bool
     */
    private $isShopware53;

    /**
     * @param Bootstrap $bootstrap
     * @param bool      $isShopware53
     */
    public function __construct(Bootstrap $bootstrap, $isShopware53)
    {
        $this->bootstrap = $bootstrap;
        $this->paypalBootstrap = $bootstrap->Collection()->get('SwagPaymentPaypal');
        $this->config = $this->paypalBootstrap->Config();
        $this->session = $bootstrap->get('session');
        $this->restClient = $bootstrap->get('paypal_plus.rest_client');
        $this->pluginLogger = $bootstrap->get('pluginlogger');
        $this->isShopware53 = $isShopware53;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onPostDispatchCheckoutSecure',
            'Enlight_Controller_Action_Frontend_Checkout_PreRedirect' => 'onPreRedirectToPayPal',
        );
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     */
    public function onPostDispatchCheckoutSecure($args)
    {
        $controller = $args->getSubject();
        $request = $controller->Request();
        $view = $controller->View();

        if ($controller->Response()->isRedirect()) {
            return;
        }

        $cameFromStep2 = $this->session->offsetGet('PayPalPlusCameFromStep2');

        if (!$cameFromStep2 && $request->getActionName() !== 'preRedirect') {
            $this->session->offsetUnset('PaypalPlusPayment');
        }

        $view->assign('isShopware53', $this->isShopware53);

        /** @var $shop \Shopware\Models\Shop\Shop */
        /** @var $shop \Shopware\Models\Shop\Shop */
        $shop = $this->bootstrap->get('shop');
        $templateVersion = $shop->getTemplate()->getVersion();

        if ($request->getActionName() === 'finish') {
            $this->addInvoiceInstructionsToView($view, $templateVersion);
        }

        $allowedActions = array(
            'confirm',
            'shippingPayment',
        );

        // Check action
        if (!in_array($request->getActionName(), $allowedActions, true)) {
            $this->session->offsetUnset('PayPalPlusCameFromStep2');

            return;
        }

        if ($request->get('ppplusRedirect')) {
            $controller->redirect(
                array(
                    'controller' => 'checkout',
                    'action' => 'payment',
                    'sAGB' => 1,
                )
            );

            return;
        }

        // Paypal plus conditions
        $user = $view->getAssign('sUserData');
        $countries = $this->bootstrap->Config()->get('paypalPlusCountries');
        if ($countries instanceof \Enlight_Config) {
            $countries = $countries->toArray();
        } else {
            $countries = (array) $countries;
        }

        if (!empty($this->session->PaypalResponse['TOKEN']) // PP-Express
            || empty($user['additional']['payment']['name'])
            || !in_array($user['additional']['country']['id'], $countries)
        ) {
            return;
        }

        if ($templateVersion < 3) { // emotion template
            $view->extendsTemplate('frontend/payment_paypal_plus/checkout.tpl');
        }

        $this->addTemplateVariables($view);

        if ($request->getActionName() === 'shippingPayment') {
            $this->session->offsetSet('PayPalPlusCameFromStep2', true);
            $this->onPaypalPlus($controller);

            return;
        }

        $view->assign('cameFromStep2', $cameFromStep2);
        $this->session->offsetUnset('PayPalPlusCameFromStep2');

        if (!$cameFromStep2 && $user['additional']['payment']['name'] === 'paypal') {
            $this->onPaypalPlus($controller);
        }
    }

    /**
     * @param \Enlight_Controller_ActionEventArgs $args
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function onPreRedirectToPayPal($args)
    {
        $controller = $args->getSubject();
        $view = $controller->View();
        $userData = $view->getAssign('sUserData');
        $paymentId = $this->session->offsetGet('PaypalPlusPayment');
        $payment = $userData['additional']['payment'];

        $this->session->sOrderVariables['sPayment'] = $payment;
        $this->session->sOrderVariables['sUserData']['additional']['payment'] = $payment;

        $customerComment = trim(strip_tags($controller->Request()->getParam('sComment')));
        if ($customerComment) {
            $this->session['sComment'] = $customerComment;
        }

        $requestData = array(
            array(
                'op' => 'add',
                'path' => '/transactions/0/item_list/shipping_address',
                'value' => $this->getShippingAddress($userData),
            ),
            array(
                'op' => 'replace',
                'path' => '/payer/payer_info',
                'value' => $this->getPayerInfo($userData),
            ),
        );

        $uri = 'payments/payment/' . $paymentId;
        $this->bootstrap->get('front')->Plugins()->ViewRenderer()->setNoRender();

        try {
            $this->restClient->patch($uri, $requestData);
        } catch (Exception $e) {
            $this->logException('An error occurred on patching the address to the payment', $e);
            throw $e;
        }

        return true;
    }

    /**
     * @param \Enlight_View_Default $view
     * @param int                   $templateVersion
     */
    private function addInvoiceInstructionsToView($view, $templateVersion)
    {
        $paymentInstructionProvider = new PaymentInstructionProvider($this->bootstrap->get('db'));
        $orderData = $view->getAssign();

        $instruction = $paymentInstructionProvider->getInstructionsByOrderNumberAndTransactionId($orderData['sOrderNumber'], $orderData['sTransactionumber']);
        $view->assign('payPalPlusInvoiceInstruction', $instruction);
        $payment = $orderData['sPayment'];

        if ($payment['name'] !== 'paypal') {
            return;
        }

        $validator = new APIValidator($this->restClient);

        if ($validator->isAPIAvailable()) {
            $payment['description'] = $this->bootstrap->Config()->get('paypalPlusDescription', '');
            $view->assign('sPayment', $payment);
        }

        if ($templateVersion < 3) {
            $view->extendsTemplate('frontend/checkout/emotion/finish.tpl');
        }
    }

    /**
     * extends the PayPal description
     *
     * @param View $view
     */
    private function addTemplateVariables(View $view)
    {
        $newDescription = $this->bootstrap->Config()->get('paypalPlusDescription', '');
        $newAdditionalDescription = $this->bootstrap->Config()->get('paypalPlusAdditionalDescription', '');
        $payments = $view->getAssign('sPayments');
        $validator = new APIValidator($this->restClient);

        if (empty($payments)) {
            return;
        }

        foreach ($payments as $key => $payment) {
            if ($payment['name'] !== 'paypal' || !$validator->isAPIAvailable()) {
                continue;
            }

            //Update the payment description
            $payments[$key]['description'] = $newDescription;
            $payments[$key]['additionaldescription'] = $payment['additionaldescription'] . $newAdditionalDescription;

            break;
        }

        $view->assign('sPayments', $payments);

        $user = $view->getAssign('sUserData');
        if (!empty($user['additional']['payment']['name']) && $user['additional']['payment']['name'] === 'paypal' && $validator->isAPIAvailable()) {
            $user['additional']['payment']['description'] = $newDescription;
            $user['additional']['payment']['additionaldescription'] = $newAdditionalDescription;
            $view->assign('sUserData', $user);
        }

        if (method_exists($this->paypalBootstrap, 'getPayment')) {
            $payPalPaymentId = $this->paypalBootstrap->getPayment()->getId();
        } else {
            //fallback for SwagPaymentPaypal version < 3.3.4
            $payPalPaymentId = $this->paypalBootstrap->Payment()->getId();
        }
        $view->assign('PayPalPaymentId', $payPalPaymentId);
    }

    /**
     * @param Controller $controller
     */
    private function onPaypalPlus(Controller $controller)
    {
        $router = $controller->Front()->Router();
        $view = $controller->View();

        $cancelUrl = $router->assemble(
            array(
                'controller' => 'payment_paypal',
                'action' => 'cancel',
                'forceSecure' => true,
            )
        );

        $returnUrl = $router->assemble(
            array(
                'controller' => 'payment_paypal',
                'action' => 'return',
                'forceSecure' => true,
            )

        );
        
        $profile = $this->getProfile();

        $uri = 'payments/payment';
        $params = array(
            'intent' => 'sale',
            'experience_profile_id' => $profile['id'],
            'payer' => array(
                'payment_method' => 'paypal',
            ),
            'transactions' => $this->getTransactionData($view->getAssign('sBasket'), $view->getAssign('sUserData')),
            'redirect_urls' => array(
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ),
        );

        $payment = array();
        try {
            $payment = $this->restClient->create($uri, $params);
        } catch (Exception $e) {
            $this->logException('An error occurred on creating a payment', $e);
        }

        if (!empty($payment['links'][1]['href'])) {
            $view->assign('PaypalPlusApprovalUrl', $payment['links'][1]['href']);
            $view->assign('PaypalPlusModeSandbox', $this->config->get('paypalSandbox'));
            $view->assign('PaypalLocale', $this->paypalBootstrap->getLocaleCode());

            $this->session->PaypalPlusPayment = $payment['id'];
        }
    }

    /**
     * @return array
     */
    private function getProfile()
    {
        if (!isset($this->session['PaypalProfile'])) {
            $profile = $this->getProfileData();
            $uri = 'payment-experience/web-profiles';
            $profileList = array();
            try {
                $profileList = $this->restClient->get($uri);
            } catch (Exception $e) {
                $this->logException('An error occurred getting the experience profiles', $e);
            }

            foreach ($profileList as $entry) {
                if ($entry['name'] === $profile['name']) {
                    $this->restClient->put("$uri/{$entry['id']}", $profile);
                    $this->session['PaypalProfile'] = array('id' => $entry['id']);
                    break;
                }
            }

            if (!isset($this->session['PaypalProfile'])) {
                $payPalProfile = null;
                try {
                    $payPalProfile = $this->restClient->create($uri, $profile);
                } catch (Exception $e) {
                    $this->logException('An error occurred on creating an experience profiles', $e);
                }
                $this->session['PaypalProfile'] = $payPalProfile;
            }
        }

        return $this->session['PaypalProfile'];
    }

    /**
     * @return array
     */
    private function getProfileData()
    {
        $template = $this->bootstrap->get('template');
        $router = $this->bootstrap->get('router');
        $shop = $this->bootstrap->get('shop');

        $localeCode = $this->paypalBootstrap->getLocaleCode(true);

        $profileName = "{$shop->getHost()}{$shop->getBasePath()}[{$shop->getId()}]";

        $shopName = $this->config->get('paypalBrandName') ?: $this->bootstrap->get('config')->get('shopName');

        // (max length 127)
        if (strlen($shopName) > 127) {
            $shopName = substr($shopName, 0, 124) . '...';
        }

        $logoImage = $this->config->get('paypalLogoImage');
        if ($logoImage !== null) {
            if ($this->paypalBootstrap->isShopware51() && !$this->paypalBootstrap->isShopware52()) {
                /** @var \Shopware\Bundle\MediaBundle\MediaService $mediaService */
                $mediaService = $this->bootstrap->get('shopware_media.media_service');
                $logoImage = $mediaService->getUrl($logoImage);
            }

            $logoImage = 'string:{link file=' . var_export($logoImage, true) . ' fullPath}';
            $logoImage = $template->fetch($logoImage);
        }

        $notifyUrl = $router->assemble(
            array(
                'controller' => 'payment_paypal',
                'action' => 'notify',
                'forceSecure' => true,
            )
        );

        return array(
            'name' => $profileName,
            'presentation' => array(
                'brand_name' => $shopName,
                'logo_image' => $logoImage,
                'locale_code' => $localeCode,
            ),
            'input_fields' => array(
                'allow_note' => true,
                'no_shipping' => 0,
                'address_override' => 1,
            ),
            'flow_config' => array(
                'bank_txn_pending_url' => $notifyUrl,
            ),
        );
    }

    private function addArticle($article)
    {
        $package_type   = $article["additional_details"]["package_id"];
        $package_weight = $article["additional_details"]["weight"];
        $quantity = $article["quantity"];

        $total += $package_type * $quantity;

        $result = [
            'name'  =>  $article["articlename"],
            'quantity' => $quantity,
            'total' => $total,
            'frost' => $article["additional_details"]["frozen_factor"],
            'weight' => $package_weight * $quantity
        ];

        return $result;
    }

    public function makeb2bBox($data,$kg = 20)
    {
        $weight = 0;
        $result = [];

        foreach ($data as $key => $value)
        {

            if($value['frost'] == 1) {
                $weight += $value['weight'];
            }

            if ($weight > 0){
                $percent = $weight*100/$kg;
                $box = floor($percent/100);
                $fraction = $percent - $box*100;
            }
        }
        $result = [
            'percent'    => $percent,
            'boxes'      => $box,
            'remTotal'   => $fraction*10
        ];

        return $result;
    }
    /**
     * Make a box
     * @param $data
     * @param int $count
     * @return array
     */
    private function makeBox($data, $count = 1000)
    {
        $total = 0;

        $result = [];

        foreach ($data as $key => $value)
        {
            if($value['frost'] == 1) {
                $total += $value['total'];
            }
        }


        if($total > 0)
        {
            $box = floor($total/$count);
            $fraction = $total % $count;

            // make boxes data
            for($i=0; $i<=$box; $i++)
            {
                $boxFill = $count;
                foreach ($data as &$valData)
                {
                    if($valData['frost'] == 1)
                    {
                        if($valData['total'] > 0)
                        {
                            $totalTemp = $boxFill;
                            if($valData['total'] < $boxFill)
                                $totalTemp = $valData['total'];

                            $boxData[$i][] = [
                                'name' => $valData['name'],
                                'total' => $totalTemp
                            ];

                            $valData['total'] = $valData['total'] - $totalTemp;
                            $boxFill = $boxFill - $totalTemp;

                            if($boxFill <= 0)
                                break;
                        }
                    }
                }
            }

            $result = [
                'boxes' => $box,
                'remTotal' => $fraction,
                'data' => $boxData,
            ];
        }

        return $result;
    }

    public function makeb2bPallet($data,$kg = 650)
    {
        $weight = 0;
        $result = [];

        foreach ($data as $key => $value)
        {

            if($value['frost'] == 1) {
                $weight += $value['weight'];
            }

            if ($weight > 0){
                $percent = $weight*100/$kg;
                $box = floor($percent/100);
                $fraction = $percent - $box*100;
            }
        }
        $result = [
            'percent'    => round($percent,2),
            'boxes'      => $box,
            'remTotal'   => round($fraction*10,2),
            'weight'     => $weight
        ];

        return $result;
    }
    /**
     * @param $basket
     * @param $user
     *
     * @return array
     */
    private function getTransactionData($basket, $user)
    {
        //TODO
        $actual_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $needle = 'pallet=1';

        $frscBoxConfig = Shopware()->Container()->get('shopware.plugin.config_reader')->getByPluginName('FrscBox');
        $articles = $basket["content"];
        $user_group = $user['additional']['user']['customergroup'];
        $zip = $user['billingaddress']['zipcode'];
        $zip = substr($zip,0,2);

        foreach ($articles as &$article) {
            $all_together[] = $this->addArticle($article);

        }
        if ($user_group === 'H') {
            if (strpos($actual_link,$needle)) {
                $palletMax = 650; //Kg
                $pallet = $this->makeb2bPallet($all_together);
                $selected = $pallet['weight'];
                $pallets = ceil($selected / $palletMax);
                $shipping = $this->getPrice($zip,$pallets);
                $total = $this->getTotalAmount($basket, $user) + $shipping;
                $edd = $this->getEstimatedDeliveryDate($basket);

            }else {
                $box = $this->makeb2bBox($all_together);
                if ($box['boxes'] >= 1) {
                    if ($box['remTotal'] >= 900 && $box['remTotal'] <= 999) {
                        $box['boxes'] += 1;
                        $shipping = $frscBoxConfig['b2bUserShippingCost'] * $box['boxes'];
                        $total = $this->getTotalAmount($basket, $user) + $shipping;
                        $edd = $this->getEstimatedDeliveryDate($basket);
                    } elseif ($box['remTotal'] == 0) {
                        $shipping = $frscBoxConfig['b2bUserShippingCost'] * $box['boxes'];
                        $total = $this->getTotalAmount($basket, $user) + $shipping;
                        $edd = $this->getEstimatedDeliveryDate($basket);

                    }
                } elseif ($box['boxes'] == 0 && $box['remTotal'] >= 900 && $box['remTotal'] <= 999) {
                    $shipping = $frscBoxConfig['b2bUserShippingCost'];
                    $total = $this->getTotalAmount($basket, $user) + $shipping;
                    $edd = $this->getEstimatedDeliveryDate($basket);
                } else {
                    $total = $this->getTotalAmount($basket, $user);
                    $shipping = $this->getTotalShipment($basket, $user);
                    $edd = $this->getEstimatedDeliveryDate($basket);
                }
            }
        }else{
            $box = $this->makeBox($all_together);
            if ($box['remTotal'] >= 500 && $box['remTotal'] < 790) {
                $shipping = $frscBoxConfig['boxShippingCost'];
                $sum = $this->getTotalAmount($basket, $user);
                $total = $sum + $shipping;
                $edd = $this->getEstimatedDeliveryDate($basket);
            }else{
                $total = $this->getTotalAmount($basket, $user);
                $shipping = $this->getTotalShipment($basket, $user);
                $edd = $this->getEstimatedDeliveryDate($basket);
            }
            

        }


        $result = array(
            array(
                'amount' => array(
                    'currency' => $this->getCurrency(),
                    'total' => number_format($total, 2),
                    'details' => array(
                        'shipping' => number_format($shipping, 2),
                        'subtotal' => number_format($total - $shipping, 2),
                        'tax' => number_format(0, 2),
                    ),
                ),
            ),
        );

        $sendCart = (bool) $this->config->get('paypalTransferCart');
        if ($sendCart) {
            $result[0]['item_list'] = array(
                'items' => $this->getItemList($basket, $user),
            );
        }

        if ($edd) {
            $result[0]['shipment_details'] = array(
                'estimated_delivery_date' => $edd,
            );
        }

        return $result;
    }

    /**
     * @param array $basket
     *
     * @return string|null
     */
    private function getEstimatedDeliveryDate(array $basket)
    {
        if (version_compare(\Shopware::VERSION, '5.2.10', '<') || count($basket['content']) === 0) {
            return null;
        }

        //Check if we have the correct attribute set up
        /** @var \Shopware\Bundle\AttributeBundle\Service\CrudService $attributeService */
        $attributeService = $this->bootstrap->get('shopware_attribute.crud_service');
        $attribute = $attributeService->get('s_articles_attributes', 'swag_paypal_estimated_delivery_date_days');

        if ($attribute === null) {
            return null;
        }

        //Calculate the highest delivery time
        $highestDeliveryTime = 0;
        foreach ($basket['content'] as $lineItem) {
            $currentDeliveryTime = $lineItem['additional_details']['swag_paypal_estimated_delivery_date_days'];

            if ($currentDeliveryTime > $highestDeliveryTime) {
                $highestDeliveryTime = $currentDeliveryTime;
            }
        }

        //We do not have any information
        if ($highestDeliveryTime === 0) {
            return null;
        }

        //Calculate the absolute delivery date by adding the days from the product attribute
        $date = new \DateTime();
        $date->add(new \DateInterval('P' . $highestDeliveryTime . 'D'));

        return $date->format('Y-m-d');
    }

    /**
     * @param $basket
     * @param $user
     *
     * @return string
     */
    private function getTotalAmount($basket, $user)
    {
        if (!empty($user['additional']['charge_vat'])) {
            return empty($basket['AmountWithTaxNumeric']) ? $basket['AmountNumeric'] : $basket['AmountWithTaxNumeric'];
        }

        return $basket['AmountNetNumeric'];
    }

    /**
     * @param $basket
     * @param $user
     *
     * @return mixed
     */
    private function getTotalShipment($basket, $user)
    {
        if (!empty($user['additional']['charge_vat'])) {
            return $basket['sShippingcostsWithTax'];
        }

        return str_replace(',', '.', $basket['sShippingcosts']);
    }

    /**
     * @return string
     */
    private function getCurrency()
    {
        return $this->bootstrap->get('currency')->getShortName();
    }

    /**
     * @param $basket
     * @param $user
     *
     * @return array
     */
    private function getItemList($basket, $user)
    {
        $list = array();
        $currency = $this->getCurrency();
        $lastCustomProduct = null;

        $index = 0;
        foreach ($basket['content'] as $basketItem) {
            $sku = $basketItem['ordernumber'];
            $name = $basketItem['articlename'];
            $quantity = (int) $basketItem['quantity'];
            if (!empty($user['additional']['charge_vat']) && !empty($basketItem['amountWithTax'])) {
                $amount = round($basketItem['amountWithTax'], 2);
            } else {
                $amount = str_replace(',', '.', $basketItem['amount']);
            }

            // If more than 2 decimal places
            if (round($amount / $quantity, 2) * $quantity != $amount) {
                if ($quantity !== 1) {
                    $name = $quantity . 'x ' . $name;
                }
                $quantity = 1;
            } else {
                $amount = round($amount / $quantity, 2);
            }

            //In the following part, we modify the CustomProducts positions.
            //By default, custom products may add alot of different positions to the basket, which would probably reach
            //the items limit of PayPal. Therefore, we group the values with the options.
            //Actually, that causes a loss of quantity precision but there is no other way around this issue but this.
            if (!empty($basketItem['customProductMode'])) {
                //A value indicating if the surcharge of this position is only being added once
                $isSingleSurcharge = $basketItem['customProductIsOncePrice'];

                switch ($basketItem['customProductMode']) {
                    /*
                     * The current basket item is of type Option (a group of values)
                     * This will be our first starting point.
                     * In this procedure we fake the amount by simply adding a %value%x to the actual name of the group.
                     * Further more, we add a : to the end of the name (if a value follows this option) to indicate that more values follow.
                     * At the end, we set the quantity to 1, so PayPal doesn't calculate the total amount. That would cause calculation errors, since we calculate the
                     * whole position already.
                     */
                    case 2: //Option
                        $nextProduct = $basket['content'][$index + 1];

                        $name = $quantity . 'x ' . $name;

                        //Another value is following?
                        if ($nextProduct && '3' === $nextProduct['customProductMode']) {
                            $name .= ': ';
                        }

                        //Calculate the total price of this option
                        if (!$isSingleSurcharge) {
                            $amount *= $quantity;
                        }

                        $quantity = 1;
                        break;

                    /*
                     * This basket item is of type Value.
                     * In this procedure we calculate the actual price of the value and add it to the option price.
                     * Further more, we add a comma to the end of the value (if another value is following) to improve the readability on the PayPal page.
                     * Afterwards, we set the quantity to 0, so that the basket item is not being added to the list. We don't have to add it again,
                     * since it's already grouped to the option.
                     */
                    case 3: //Value
                        //The last option that has been added to the final list.
                        //This value will be grouped to it.
                        $lastGroup = &$list[count($list) - 1];
                        $nextProduct = $basket['content'][$index + 1];

                        if ($lastGroup) {
                            //Check if another value is following, if so, add a comma to the end of the name.
                            if ($nextProduct && '3' === $nextProduct['customProductMode']) {
                                //Another value is following
                                $lastGroup['name'] .= $name . ', ';
                            } else {
                                //This is the last value in this option
                                $lastGroup['name'] .= $name;
                            }

                            //Calculate the total price.
                            $lastGroup['price'] += $isSingleSurcharge ? $amount : $amount * $quantity;

                            //Don't add it to the final list
                            $quantity = 0;
                        }
                        break;
                }
            }

            if ($quantity !== 0) {
                $item = array(
                    'name' => $name,
                    'price' => number_format($amount, 2),
                    'currency' => $currency,
                    'quantity' => $quantity,
                );

                if ($sku !== null && $sku !== '') {
                    $item['sku'] = $sku;
                }

                $list[] = $item;
            }

            ++$index;
        }

        return $list;
    }

    /**
     * @param array $user
     *
     * @return array
     */
    private function getShippingAddress(array $user)
    {
        $address = array(
            'recipient_name' => $user['shippingaddress']['firstname'] . ' ' . $user['shippingaddress']['lastname'],
            'line1' => trim($user['shippingaddress']['street'] . ' ' . $user['shippingaddress']['streetnumber']),
            'city' => $user['shippingaddress']['city'],
            'postal_code' => $user['shippingaddress']['zipcode'],
            'country_code' => $user['additional']['countryShipping']['countryiso'],
            'state' => $user['additional']['stateShipping']['shortcode'],
        );

        return $address;
    }

    /**
     * @param array $user
     *
     * @return array
     */
    private function getPayerInfo(array $user)
    {
        $payerInfo = array(
            'country_code' => $user['additional']['country']['countryiso'],
            'email' => $user['additional']['user']['email'],
            'first_name' => $user['billingaddress']['firstname'],
            'last_name' => $user['billingaddress']['lastname'],
            'phone' => $user['billingaddress']['phone'],
            'billing_address' => $this->getBillingAddress($user),
        );

        return $payerInfo;
    }

    /**
     * @param array $user
     *
     * @return array
     */
    private function getBillingAddress(array $user)
    {
        $billingAddress = array(
            'line1' => trim($user['billingaddress']['street'] . ' ' . $user['billingaddress']['streetnumber']),
            'postal_code' => $user['billingaddress']['zipcode'],
            'city' => $user['billingaddress']['city'],
            'country_code' => $user['additional']['country']['countryiso'],
            'state' => $user['additional']['state']['shortcode'],
        );

        return $billingAddress;
    }

    /**
     * Writes an exception to the plugin log.
     *
     * @param string    $message
     * @param Exception $e
     */
    private function logException($message, Exception $e)
    {
        $logger = new LoggerService($this->pluginLogger);
        $logger->log($message, $e);
    }

    private function getPrice($zip, $boxes)
    {
        $tarif = [
            01 => [
                1 => 200,
                2 => 158,
                3 => 0,
            ],
            02 => [
                1 => 195,
                2 => 170,
                3 => 0,
            ],
            03 => [
                1 => 200,
                2 => 156,
                3 => 0,
            ],
            04 => [
                1 => 190,
                2 => 137,
                3 => 0
            ],
            06 => [
                1 => 170,
                2 => 124,
                3 => 0,
            ],
            07 => [
                1 => 180,
                2 => 129,
                3 => 0
            ],
            '08' => [
                1 => 190,
                2 => 139,
                3 => 0
            ],
            '09' => [
                1 => 185,
                2 => 145,
                3 => 0
            ],
            10 => [
                1 => 185,
                2 => 135
            ],
            12 => [
                1 => 190,
                2 => 137,
                3 => 0
            ],
            13 => [
                1 => 185,
                2 => 145,
                3 => 0,
            ],
            14 => [
                1 => 185,
                2 => 135,
                3 => 0,
            ],
            15 => [
                1 => 180,
                2 => 143,
                3 => 0,
            ],
            16 => [
                1 => 195,
                2 => 154,
                3 => 0,
            ],
            17 => [
                1 => 190,
                2 => 137,
                3 => 0,
            ],
            18 => [
                1 => 180,
                2 => 131,
                3 => 0,
            ],
            19 => [
                1 => 165,
                2 => 109,
                3 => 0,
            ],
            20 => [
                1 => 140,
                2 => 145,
                3 => 0,
            ],
            21 => [
                1 => 145,
                2 => 88,
                3 => 0,
            ],
            22 => [
                1 => 140,
                2 => 87,
                3 => 0,
            ],
            23 => [
                1 => 160,
                2 => 99,
                3 => 0,
            ],
            24 => [
                1 => 160,
                2 => 105,
                3 => 0,
            ],
            25 => [
                1 => 160,
                2 => 105,
                3 => 0,
            ],
            26 => [
                1 => 125,
                2 => 73,
                3 => 0,
            ],
            27 => [
                1 => 135,
                2 => 79,
                3 => 0,
            ],
            28 => [
                1 => 120,
                2 => 66,
                3 => 0,
            ],
            29 => [
                1 => 150,
                2 => 93,
                3 => 0,
            ],
            30 => [
                1 => 140,
                2 => 79,
                3 => 0,
            ],
            31 => [
                1 => 130,
                2 => 81,
                3 => 0,
            ],
            32 => [
                1 => 120,
                2 => 64,
                3 => 0,
            ],
            33 => [
                1 => 120,
                2 => 64,
                3 => 0,
            ],
            34 => [
                1 => 140,
                2 => 87,
                3 => 0,
            ],
            35 => [
                1 => 160,
                2 => 105,
                3 => 0,
            ],
            36 => [
                1 => 160,
                2 => 105,
                3 => 0,
            ],
            37 => [
                1 => 155,
                2 => 97,
                3 => 0,
            ],
            38 => [
                1 => 155,
                2 => 97,
                3 => 0,
            ],
            39 => [
                1 => 170,
                2 => 114,
                3 => 0,
            ],
            40 => [
                1 => 135,
                2 => 83,
                3 => 0,
            ],
            41 => [
                1 => 145,
                2 => 89,
                3 => 0,
            ],
            42 => [
                1 => 130,
                2 => 81,
                3 => 0,
            ],
            44 => [
                1 => 130,
                2 => 74,
                3 => 0,
            ],
            45 => [
                1 => 130,
                2 => 76,
                3 => 0,
            ],
            46 => [
                1 => 135,
                2 => 78,
                3 => 0,
            ],
            47 => [
                1 => 140,
                2 => 79,
                3 => 0,
            ],
            48 => [
                1 => 120,
                2 => 64,
                3 => 0,
            ],
            49 => [
                1 => 120,
                2 => 54,
                3 => 0,
            ],
            50 => [
                1 => 145,
                2 => 89,
                3 => 0,
            ],
            51 => [
                1 => 140,
                2 => 85,
                3 => 0,
            ],
            52 => [
                1 => 155,
                2 => 97,
                3 => 0,
            ],
            53 => [
                1 => 150,
                2 => 93,
                3 => 0,
            ],
            54 => [
                1 => 175,
                2 => 118,
                3 => 0,
            ],
            55 => [
                1 => 170,
                2 => 124,
                3 => 0,
            ],
            56 => [
                1 => 160,
                2 => 108,
                3 => 0,
            ],
            57 => [
                1 => 145,
                2 => 91,
                3 => 0,
            ],
            58 => [
                1 => 135,
                2 => 77,
                3 => 0,
            ],
            59 => [
                1 => 135,
                2 => 77,
                3 => 0,
            ],
            60 => [
                1 => 175,
                2 => 118,
                3 => 0,
            ],
            61 => [
                1 => 170,
                2 => 112,
                3 => 0,
            ],
            63 => [
                1 => 170,
                2 => 123,
                3 => 0,
            ],
            64 => [
                1 => 180,
                2 => 131,
                3 => 0,
            ],
            65 => [
                1 => 160,
                2 => 108,
                3 => 0,
            ],
            66 => [
                1 => 180,
                2 => 141,
                3 => 0,
            ],
            67 => [
                1 => 175,
                2 => 127,
                3 => 0,
            ],
            68 => [
                1 => 180,
                2 => 131,
                3 => 0,
            ],
            69 => [
                1 => 185,
                2 => 133,
                3 => 0,
            ],
            70 => [
                1 => 200,
                2 => 158,
                3 => 0,
            ],
            71 => [
                1 => 200,
                2 => 156,
                3 => 0,
            ],
            72 => [
                1 => 200,
                2 => 175,
                3 => 0,
            ],
            73 => [
                1 => 190,
                2 => 161,
                3 => 0,
            ],
            74 => [
                1 => 190,
                2 => 149,
                3 => 0,
            ],
            75 => [
                1 => 185,
                2 => 147,
                3 => 0,
            ],
            76 => [
                1 => 180,
                2 => 143,
                3 => 0,
            ],
            77 => [
                1 => 205,
                2 => 161,
                3 => 0,
            ],
            78 => [
                1 => 210,
                2 => 184,
                3 => 0,
            ],
            79 => [
                1 => 200,
                2 => 172,
                3 => 0,
            ],
            80 => [
                1 => 215,
                2 => 185,
                3 => 0,
            ],
            81 => [
                1 => 215,
                2 => 187,
                3 => 0,
            ],
            82 => [
                1 => 225,
                2 => 194,
                3 => 0,
            ],
            83 => [
                1 => 230,
                2 => 198,
                3 => 0,
            ],
            84 => [
                1 => 220,
                2 => 192,
                3 => 0,
            ],
            85 => [
                1 => 210,
                2 => 182,
                3 => 0,
            ],
            86 => [
                1 => 205,
                2 => 179,
                3 => 0,
            ],
            87 => [
                1 => 215,
                2 => 185,
                3 => 0,
            ],
            88 => [
                1 => 210,
                2 => 182,
                3 => 0,
            ],
            89 => [
                1 => 190,
                2 => 163,
                3 => 0,
            ],
            90 => [
                1 => 185,
                2 => 147,
                3 => 0,
            ],
            91 => [
                1 => 180,
                2 => 143,
                3 => 0,
            ],
            92 => [
                1 => 195,
                2 => 170,
                3 => 0,
            ],
            93 => [
                1 => 205,
                2 => 177,
                3 => 0,
            ],
            94 => [
                1 => 215,
                2 => 189,
                3 => 0,
            ],
            95 => [
                1 => 195,
                2 => 154,
                3 => 0,
            ],
            96 => [
                1 => 190,
                2 => 139,
                3 => 0,
            ],
            97 => [
                1 => 170,
                2 => 122,
                3 => 0,
            ],
            98 => [
                1 => 175,
                2 => 118,
                3 => 0,
            ],
            99 => [
                1 => 175,
                2 => 118,
                3 => 0,
            ],
        ];


        return $tarif[$zip][$boxes];
    }

}
