<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

use Shopware\Components\BasketSignature\BasketPersister;
use Shopware\Components\BasketSignature\BasketSignatureGeneratorInterface;
use Shopware\Components\Random;

/**
 * Shopware Payment Controller
 *
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
abstract class Shopware_Controllers_Frontend_Payment extends Enlight_Controller_Action
{
    /**
     * Returns the current payment short name.
     *
     * @return string
     */
    public function getPaymentShortName()
    {
        if (($user = $this->getUser()) !== null
                && !empty($user['additional']['payment']['name'])) {
            return $user['additional']['payment']['name'];
        }

        return null;
    }

    /**
     * Returns the current currency short name.
     *
     * @return string
     */
    public function getCurrencyShortName()
    {
        return Shopware()->Container()->get('currency')->getShortName();
    }

    /**
     * Creates a unique payment id and returns it then.
     *
     * @return string
     */
    public function createPaymentUniqueId()
    {
        return Random::getAlphanumericString(32);
    }

    /**
     * Stores the final order and does some more actions accordingly.
     *
     * @param string $transactionId
     * @param string $paymentUniqueId
     * @param int    $paymentStatusId
     * @param bool   $sendStatusMail
     *
     * @return int
     */
    public function saveOrder($transactionId, $paymentUniqueId, $paymentStatusId = null, $sendStatusMail = false)
    {
        if (empty($transactionId) || empty($paymentUniqueId)) {
            return false;
        }

        $sql = '
            SELECT ordernumber FROM s_order
            WHERE transactionID=? AND temporaryID=?
            AND status!=-1 AND userID=?
        ';
        $orderNumber = Shopware()->Db()->fetchOne($sql, [
                $transactionId,
                $paymentUniqueId,
                Shopware()->Session()->sUserId,
            ]);

        if (empty($orderNumber)) {
            $user = $this->getUser();
            $basket = $this->getBasket();

            $order = Shopware()->Modules()->Order();
            $order->sUserData = $user;
            $order->sComment = Shopware()->Session()->sComment;
            $order->sBasketData = $basket;
            $order->sAmount = $basket['sAmount'];
            $order->sAmountWithTax = !empty($basket['AmountWithTaxNumeric']) ? $basket['AmountWithTaxNumeric'] : $basket['AmountNumeric'];
            $order->sAmountNet = $basket['AmountNetNumeric'];
            $order->sShippingcosts = $basket['sShippingcosts'];
            $order->sShippingcostsNumeric = $basket['sShippingcostsWithTax'];
            $order->sShippingcostsNumericNet = $basket['sShippingcostsNet'];
            $order->bookingId = $transactionId;
            $order->dispatchId = Shopware()->Session()->sDispatch;
            $order->sNet = empty($user['additional']['charge_vat']);
            $order->uniqueID = $paymentUniqueId;
            $order->deviceType = $this->Request()->getDeviceType();
            $orderNumber = $order->sSaveOrder();
        }

        if (!empty($orderNumber) && !empty($paymentStatusId)) {
            $this->savePaymentStatus($transactionId, $paymentUniqueId, $paymentStatusId, $sendStatusMail);
        }

        return $orderNumber;
    }

    /**
     * Saves the payment status an sends and possibly sends a status email.
     *
     * @param string $transactionId
     * @param string $paymentUniqueId
     * @param int    $paymentStatusId
     * @param bool   $sendStatusMail
     */
    public function savePaymentStatus($transactionId, $paymentUniqueId, $paymentStatusId, $sendStatusMail = false)
    {
        $sql = '
            SELECT id FROM s_order
            WHERE transactionID=? AND temporaryID=?
            AND status!=-1
        ';
        $orderId = Shopware()->Db()->fetchOne($sql, [
                $transactionId,
                $paymentUniqueId,
            ]);
        $order = Shopware()->Modules()->Order();
        $order->setPaymentStatus($orderId, $paymentStatusId, $sendStatusMail);
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
     * Return the full amount to pay.
     *
     * @return float
     */
    public function getAmount()
    {
        $user = $this->getUser();
        $basket = $this->getBasket();
        $frscBoxConfig = Shopware()->Container()->get('shopware.plugin.config_reader')->getByPluginName('FrscBox');
        $articles =  $basket["content"];
        $user_group = $user['additional']['user']['customergroup'];
        $userID = $user['billingaddress']['userID'];

        foreach ($articles as &$article) {
            $all_together[] = $this->addArticle($article);

        }
        if ($user_group === 'H') {
            $sql = "SELECT id,comment FROM s_order WHERE userID = $userID ORDER BY ID DESC LIMIT 1";
            $last_inserted = Shopware()->Db()->fetchAll($sql);
            if($last_inserted[0]["comment"] === 'pallet'){
                $palletMax = 650; //Kg

                $zip = $user['billingaddress']['zipcode'];
                $zip = substr($zip,0,2);

                $pallet = $this->makeb2bPallet($all_together);

                $selected = $pallet['weight'];
                $pallets = ceil($selected / $palletMax);
                $shipping = $this->getPrice($zip, $pallets);
                $total = empty($basket['AmountWithTaxNumeric']) ? $basket['AmountNumeric'] : $basket['AmountWithTaxNumeric'];
                $sum = $total + $shipping;

                return $sum;
            }else {
                $box = $this->makeb2bBox($all_together);
                if ($box['boxes'] >= 1) {
                    if ($box['remTotal'] >= 900 && $box['remTotal'] <= 999) {
                        $box['boxes'] += 1;
                        $shipping = $frscBoxConfig['b2bUserShippingCost'] * $box['boxes'];
                        $total = empty($basket['AmountWithTaxNumeric']) ? $basket['AmountNumeric'] : $basket['AmountWithTaxNumeric'];
                        $sum = $total + $shipping;

                        return $sum;
                    } elseif ($box['remTotal'] == 0) {
                        $shipping = $frscBoxConfig['b2bUserShippingCost'] * $box['boxes'];
                        $total = empty($basket['AmountWithTaxNumeric']) ? $basket['AmountNumeric'] : $basket['AmountWithTaxNumeric'];
                        $sum = $total + $shipping;

                        return $sum;

                    }
                } elseif ($box['boxes'] == 0 && $box['remTotal'] >= 900 && $box['remTotal'] <= 999) {
                    $shipping = $frscBoxConfig['b2bUserShippingCost'];
                    $total = empty($basket['AmountWithTaxNumeric']) ? $basket['AmountNumeric'] : $basket['AmountWithTaxNumeric'];
                    $sum = $total + $shipping;

                    return $sum;
                } else {
                    if (!empty($user['additional']['charge_vat'])) {
                        return empty($basket['AmountWithTaxNumeric']) ? $basket['AmountNumeric'] : $basket['AmountWithTaxNumeric'];
                    }

                    return $basket['AmountNetNumeric'];
                }
            }
        }else{
            $box = $this->makeBox($all_together);
            if ($box['remTotal'] >= 500 && $box['remTotal'] < 790) {
                $shipping = $frscBoxConfig['boxShippingCost'];
                $total = empty($basket['AmountWithTaxNumeric']) ? $basket['AmountNumeric'] : $basket['AmountWithTaxNumeric'];
                $sum = $total + $shipping;

                return $sum;
            }else{
                if (!empty($user['additional']['charge_vat'])) {
                    return empty($basket['AmountWithTaxNumeric']) ? $basket['AmountNumeric'] : $basket['AmountWithTaxNumeric'];
                }

                return $basket['AmountNetNumeric'];
            }


        }
        if (!empty($user['additional']['charge_vat'])) {
            return empty($basket['AmountWithTaxNumeric']) ? $basket['AmountNumeric'] : $basket['AmountWithTaxNumeric'];
        }

        return $basket['AmountNetNumeric'];

    }

    /**
     * Returns shipment amount as float
     *
     * @return float
     */
    public function getShipment()
    {
        $user = $this->getUser();
        $basket = $this->getBasket();
        if (!empty($user['additional']['charge_vat'])) {
            return $basket['sShippingcostsWithTax'];
        }

        return str_replace(',', '.', $basket['sShippingcosts']);
    }

    /**
     * Returns the full user data as array.
     *
     * @return array
     */
    public function getUser()
    {
        if (!empty(Shopware()->Session()->sOrderVariables['sUserData'])) {
            return Shopware()->Session()->sOrderVariables['sUserData'];
        }

        return null;
    }

    /**
     * Returns the full basket data as array.
     *
     * @return array
     */
    public function getBasket()
    {
        if (!empty(Shopware()->Session()->sOrderVariables['sBasket'])) {
            return Shopware()->Session()->sOrderVariables['sBasket'];
        }

        return null;
    }

    /**
     * @return string|null
     */
    public function getOrderNumber()
    {
        if (!empty(Shopware()->Session()->sOrderVariables['sOrderNumber'])) {
            return Shopware()->Session()->sOrderVariables['sOrderNumber'];
        }

        return null;
    }

    /**
     * @return string
     */
    protected function persistBasket()
    {
        /** @var Enlight_Components_Session_Namespace $session */
        $session = $this->get('session');
        $basket = $session->offsetGet('sOrderVariables')->getArrayCopy();
        $customerId = $session->offsetGet('sUserId');

        /** @var BasketSignatureGeneratorInterface $signatureGenerator */
        $signatureGenerator = $this->get('basket_signature_generator');
        $signature = $signatureGenerator->generateSignature(
            $basket['sBasket'],
            $customerId
        );

        /** @var BasketPersister $persister */
        $persister = $this->get('basket_persister');
        $persister->persist($signature, $basket);

        return $signature;
    }

    /**
     * Loads the persisted basket identified by the given signature.
     * Persisted basket will be removed from storage after loading.
     * Converted ArrayObject for shopware session is already created and stored in session for following checkout processes.
     *
     * @param string $signature
     *
     * @return ArrayObject
     */
    protected function loadBasketFromSignature($signature)
    {
        /** @var BasketPersister $persister */
        $persister = $this->get('basket_persister');
        $data = $persister->load($signature);

        if (!$data) {
            throw new RuntimeException(sprintf('Basket for signature %s not found', $signature));
        }

        $persister->delete($signature);

        $basket = new ArrayObject($data, ArrayObject::ARRAY_AS_PROPS);
        $this->get('session')->offsetSet('sOrderVariables', $basket);

        return $basket;
    }

    /**
     * @param string      $signature
     * @param ArrayObject $basket
     *
     * @throws RuntimeException if signature does not match with provided basket
     */
    protected function verifyBasketSignature($signature, ArrayObject $basket)
    {
        /** @var BasketSignatureGeneratorInterface $generator */
        $generator = $this->get('basket_signature_generator');

        $data = $basket->getArrayCopy();

        $newSignature = $generator->generateSignature(
            $data['sBasket'],
            $this->get('session')->get('sUserId')
        );

        if ($newSignature !== $signature) {
            throw new RuntimeException('The given signature is not equal to the generated signature of the saved basket');
        }
    }

    /**
     * @param string $paymentName
     * @param string $orderNumber
     * @param string $transactionNumber
     */
    protected function sendSignatureIsInvalidNotificationMail($paymentName, $orderNumber, $transactionNumber)
    {
        $content = <<<'EOD'
An invalid basket signature occurred during a customers checkout. Please verify the order.
Following information may help you to identify the problem:<br>
Payment method: %s. <br>
Order number: %s.<br>
Payment transaction number: %s.
EOD;

        $content = sprintf($content, $paymentName, $orderNumber, $transactionNumber);

        try {
            /** @var Enlight_Components_Mail $mail */
            $mail = $this->get('mail');
            $mail->addTo($this->get('config')->get('mail'));
            $mail->setSubject('An invalid basket signature occured');
            $mail->setBodyHtml($content);
            $mail->send();
        } catch (Exception $e) {
        }

        /** @var \Shopware\Components\Logger $logger */
        $logger = $this->get('corelogger');
        $logger->log('error', $content);
    }

    public function getPrice($zip, $boxes)
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
