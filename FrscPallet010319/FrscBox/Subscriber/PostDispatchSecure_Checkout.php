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
            "Shopware_Controllers_Frontend_Checkout::confirmAction::after" => "palletCalc2",
//            "sArticles::sGetArticleById::after" => "ttt"
            'Shopware_Controllers_Frontend_Detail::indexAction::before' => 'replacedPrepareVariantData'
        );
    }

    public function replacedPrepareVariantData(\Enlight_Hook_HookArgs $args)
    {
        $controller = $args->getSubject();
        $view = $controller->View();

        $actual_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $array = explode('/',$actual_link);
        $article_name = array_pop($array);

        $article_folder = 'media/users-images/'.$article_name;
        if (file_exists($article_folder))
        {
            $files = array_diff(scandir($article_folder),['..','.']);
            foreach ($files as $file)
            {
                $result[] = $article_folder.'/'.$file;
            }

        }
        $view->assign('user_images',$result);
    }
    

    public function palletCalc2(\Enlight_Hook_HookArgs $args)
    {

        $controller = $args->getSubject();
        $view = $controller->View();

        $actual_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $needle = 'pallet=1';
        if (strpos($actual_link,$needle)) {

            $db = Shopware()->Db();
            $sql = "SELECT id FROM s_order ORDER BY ID DESC LIMIT 1";
            $last_order_id = $db->fetchAll($sql);

            $db->executeUpdate(
                "UPDATE s_order SET comment = ? WHERE id = ?",
                array('pallet',$last_order_id[0]["id"])
            );


            $view->assign('palletExist',true);

            $basket = Shopware()->Modules()->Basket()->sGetBasket();
            $articles = $basket["content"];
            foreach ($articles as &$article) {
                $result[] = $this->addArticle($article);
            }
            $pallet = $this->makeb2bPallet($result);

            $user = Shopware()->Modules()->Admin()->sGetUserData();
            $view->assign('palletTest', $pallet);

            $userID =  $user['billingaddress']['userID'];
            $zip = $user['billingaddress']['zipcode'];
            $zip = substr($zip,0,2);
            $view->assign('userZip', $zip);


            $palletMax = 650; //Kg
            $selected = $pallet['weight'];
            $box = ceil($selected / $palletMax);
            $view->assign('palletBoxes', $box);
            include_once 'Tarif.php';

            $pallet_price = getPrice($zip, $box);
            $view->assign('pallet_price',$pallet_price);

        }

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
        $user = Shopware()->Modules()->Admin()->sGetUserData();
        if(Shopware()->Modules()->Admin()->sCheckUser()){
            $user_group = Shopware()->Session()->sUserGroup;
        }

        $articles = $basket["content"];
        $view = $controller->View();
        $view->assign("userMaric", $user_group);
        $view->assign("frscBox", $config);

        $totallBoxWeight = $dispatches["weight"];
        $amount = str_replace(',','.',$basket['Amount']);




        if ($user_group === 'H' || $user_group === 'DOGS'){
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

        if ($user_group === 'H' || $user_group === 'DOGS'){               //b2b users
            include_once 'Tarif.php';

            $zip = $user['billingaddress']['zipcode'];
            $zip = substr($zip,0,2);
            $box = $this->makeb2bBox($result);
            $pallet = $this->makeb2bPallet($result);

            $palletMax = 650; //Kg
            $selected = $pallet['weight'];
            $pallets = ceil($selected / $palletMax);

            //TODO fix zip to be a string
            $pallet_price = getPrice($zip, $pallets);
            $pallet_sum = $pallet_price + $basket['Amount'];

            $view->assign('pallet_price',$pallet_price);
            $view->assign('pallet_sum',$pallet_sum);
            $view->assign('b2bUser',true);
            $view->assign('pallet',$pallet);
            if ($basket['Amount'] < 150){
                $boxError = true;
                $view->assign('boxError', $boxError);
            }
            if ($box['remTotal'] < 899 && $box['remTotal'] > 1){
                $boxError = true;
                $view->assign('boxError', $boxError);
//            $view->assign("PaypalShowButton", false);
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
                    $shipping = 0;
//            $view->assign("PaypalShowButton", false);
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
            $url = $_SERVER['REQUEST_URI'];
            $view->assign("mDispatch", $totallBoxWeight);
            $view->assign("frost_faktor", $frost_factor);
            $view->assign('boxcount', $box);
            $view->assign('urlBox', $url);
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
}
