<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Model\Config\Source;

class PaymentOptions implements \Magento\Framework\Data\OptionSourceInterface
{

    public function toOptionArray()
    {
        return [
            ['value' => 'pisp', 'label' => __('Bank')],
        //    ['value' => 'card', 'label' => __('Cards')],
        //    ['value' => 'card,pisp', 'label' => __('Cards & Bank')],
        ];
    }

}
