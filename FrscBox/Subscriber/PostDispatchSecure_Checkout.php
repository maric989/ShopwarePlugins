<?php

namespace FrscBox\Subscriber;

use Enlight\Event\SubscriberInterface;
use Shopware_Controllers_Frontend_Checkout;
class PostDispatchSecure_Checkout implements SubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return array(
            "Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout" => "productBoxes",
            "Shopware_Controllers_Frontend_Checkout::finishAction::after" => "finish",
//            "Enlight_Controller_Action_PostDispatchSecure_Frontend_PaymentPaypal" => "test2",
//            "Shopware_Controllers_Frontend_PaymentPaypal::forward::before" => "test2"
//            "Shopware_Controllers_Frontend_Checkout::confirmAction::after" => "test6",
//            "Shopware_Controllers_Frontend_Checkout::confirmAction::before" => "test6",
//            "Shopware\SwagPaymentPaypalPlus\Subscriber::onPaypalPlus::before" => "test6",
//            "Shopware_Controllers_Frontend_PaymentPaypalPlus::getBasketParameter::before" => "test"
//            'Shopware_Plugins_Frontend_SwagPaymentPaypalPlus::' => 'test6'

        );
    }

    public function test6(\Enlight_Hook_HookArgs $args)
    {
//        $test = new Shopware\SwagPaymentPaypalPlus\Subscriber();
//
//        die(var_dump($test));

    }

    public function afterGetBasketParameter(\Enlight_Event_EventArgs $args)
    {

        $return = $args->getReturn();

            $shippingcosts = 1000;
            if ($shippingcosts) {
                $return['PAYMENTREQUEST_0_SHIPPINGAMT'] = $shippingcosts;
                $return['PAYMENTREQUEST_0_AMT'] += $return['PAYMENTREQUEST_0_SHIPPINGAMT'];
            }


        $args->setReturn($return);
    }


    public function finish(\Enlight_Event_EventArgs $args)
    {
        $controller = $args->getSubject();
        $view = $controller->View();

        $sql = "SELECT * FROM s_order ORDER BY ID DESC LIMIT 1";
        $last_order = Shopware()->Db()->fetchAll($sql);
        $view->assign('last_order',$last_order);
    }

    public function productBoxes(\Enlight_Event_EventArgs $args)
    {
        /** @var $controller \Enlight_Controller_Action */

        $controller = $args->getSubject();
        $request = $controller->Request();
        $config = Shopware()->Container()->get('shopware.plugin.config_reader')->getByPluginName('FrscBox');

        $basket = Shopware()->Modules()->Basket()->sGetBasket();
        $dispatches = Shopware()->Modules()->Admin()->sGetDispatchBasket();
//        $user = Shopware()->Modules()->Admin()->sGetUserData();
//        $user_group = $user['additional']['user']['customergroup'];
        if(Shopware()->Modules()->Admin()->sCheckUser()){
            $user_group = Shopware()->Session()->sUserGroup;
        }


//        die(var_dump($basket['sShippingcostsWithTax']));
//        die(var_dump($basket['Amount']));
        $articles = $basket["content"];
        $view = $controller->View();
        $view->assign("userMaric", $user_group);
        $view->assign("frscBox", $config);

        $totallBoxWeight = $dispatches["weight"];
        $amount = str_replace(',','.',$basket['Amount']);

        if ($user_group === 'H'){
            $shipping = $config['b2bUserShippingCost'];
            $view->assign('dostava2',$shipping);

        }else{
            $shipping = $config['boxShippingCost'];
        }
        foreach ($articles as &$article) {
            $result[] = $this->addArticle($article);
            $frost_factor = $article["additional_details"]["frozen_factor"];
        }

        $view->assign("rezultat", $result);

        if ($user_group === 'H'){               //b2b users
            $box = $this->makeb2bBox($result);
            $view->assign('b2bUser',true);
            if ($box['remTotal'] < 899 && $box['remTotal'] > 1){
                $boxError = true;
                $view->assign('boxError', $boxError);
            $view->assign("PaypalShowButton", false);
            }

            if ($box['boxes'] >= 1) {
                if ($box['remTotal'] >= 900 && $box['remTotal'] <= 999) {
                    $shipping = $config['b2bUserShippingCost'] * ($box['boxes']+1);
                    $view->assign('dostava',$shipping);
                    $price = $amount + $shipping;
                    $view->assign('shippingExist',true);
                    $view->assign('transport',$price);
                }elseif($box['remTotal'] == 0){
                    $shipping = $config['b2bUserShippingCost'] * ($box['boxes']);
                    $view->assign('dostava',$shipping);
                    $price = $amount + $shipping;
                    $view->assign('shippingExist',true);
                    $view->assign('transport',$price);
                }
            }else {
                if ($box['remTotal'] >= 900 && $box['remTotal'] <= 999) {
                    $shipping = $config['b2bUserShippingCost'];
                    $price = $amount + $shipping;
                    $view->assign('dostava',$shipping);
                    $view->assign('shippingExist',true);
                    $view->assign('transport',$price);
                }
            }
            $view->assign("mDispatch", $totallBoxWeight);
            $view->assign("frost_faktor", $frost_factor);
            $view->assign('boxcount', $box);
            if ($box["boxes"] != 0) {
                $view->assign('sumBoxes', range(1, $box["boxes"]));
            }
        }else{  // rest users
            $box = $this->makeBox($result);
            if ($box['boxes'] == 0){
                if ($box['remTotal'] < 799 && $box['remTotal'] > 0){
                    $boxError = true;
                    $zeroBox = 'Das erste Paket muss mindestens zu 80% befüllt sein.';
                    $view->assign('boxError', $boxError);
                    $view->assign('zeroBox', $zeroBox);
            $view->assign("PaypalShowButton", false);
                }
                if ($box['remTotal'] >= 800 && $box['remTotal'] < 999){
                    $price = $amount + $shipping;
//                    $zeroBoxShipping = 'Für eine nicht zu 100% befüllte Box berechnen wir '.$shipping.' EUR Versandkosten';
//                    $view->assign('dostava',$shipping);
//                    $view->assign('shippingExist',true);
//                    $view->assign('transport',$price);
//                    $view->assign('zeroBoxShipping',$zeroBoxShipping);
                }
            }else {
                if ($box['remTotal'] < 499 && $box['remTotal'] > 0) {
                    $boxError = true;
                    $view->assign('boxError', $boxError);
                    //            $view->assign("PaypalShowButton", false);
                }

                if ($box['remTotal'] >= 500 && $box['remTotal'] < 790) {
                    $price = $amount + $shipping;
                    $view->assign('dostava', $shipping);
                    $view->assign('shippingExist', true);
                    $view->assign('transport', $price);
                }
            }
            $view->assign("mDispatch", $totallBoxWeight);
            $view->assign("frost_faktor", $frost_factor);
            $view->assign('boxcount', $box);
            if ($box["boxes"] != 0) {
                $view->assign('sumBoxes', range(1, $box["boxes"]));
            }
        }

    }

    /**
     * Adding article to basket
     * @param $article
     * @return array
     */
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

    public function test(\Enlight_Event_EventArgs $args)
    {
//        die('ssss');
//        $return = $args->getReturn();

//        $shippingcosts = 1000;
//        if ($shippingcosts) {
//            $return['PAYMENTREQUEST_0_SHIPPINGAMT'] = $shippingcosts;
//            $return['PAYMENTREQUEST_0_AMT'] += $return['PAYMENTREQUEST_0_SHIPPINGAMT'];
//        }
//
//
//        $args->setReturn($return);
//        $session = Shopware()->Session();
////        $session['sOrderVariables'] = 1000;
////        $session['sOrderVariables']['sDispatch']['amount'] = 1000;
//        $session['sOrderVariables']['sBasket']['content']['0']['amountWithTax'] = 1000;
////        die(print_r($session['sOrderVariables']['sBasket']['content']['0']['amountWithTax']));
        // $sql = "SELECT * FROM s_order ORDER BY ID DESC LIMIT 1";
//        $last_order = Shopware()->Db()->fetchAll($sql);
//        $id = $last_order[0]['id'];
//        $ammount = 8000;
        $id = Shopware()->Session()['sessionId'];
//        die(var_dump($id));
        $sql2= "UPDATE s_order_basket SET price = 5 where sessionId='".$id."'";
        Shopware()->Db()->executeQuery($sql2);
        $basket = Shopware()->Modules()->Basket()->sGetBasket();
//        $basket['AmountWithTax'] = 1000;
//        $basket['sAmountWithTax'] = 1200;


        //die(var_dump($basket['AmountWithTax']));
    }
}
