<?php
declare(strict_types=1);

namespace Nofrixion\Payments\Model\Payment;

class Nofrixion extends \Magento\Payment\Model\Method\AbstractMethod
{

    const CODE = 'nofrixion';
    protected $_code = self::CODE;

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    )
    {
        return parent::isAvailable($quote);
    }
//
//    public function postRequest(DataObject $request, ConfigInterface $config)
//    {
//        $r = new DataObject(
//            [
//                'result' => '0',
//                'pnref' => 'V19A3D27B61E',
//                'respmsg' => 'Approved',
//                'authcode' => '510PNI',
//                'hostcode' => 'A',
//                'request_id' => 'f930d3dc6824c1f7230c5529dc37ae5e',
//                'result_code' => '0'
//            ]
//        );
//        return $r;
//    }
}

