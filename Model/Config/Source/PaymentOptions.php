<?php

declare(strict_types=1);

namespace NoFrixion\Payments\Model\Config\Source;

class PaymentOptions implements \Magento\Framework\Data\OptionSourceInterface
{

    public function toOptionArray()
    {
        return [
            ['value' => 'pisp', 'label' => __('Banks')],
            ['value' => 'card', 'label' => __('Cards')],
            ['value' => 'card,pisp', 'label' => __('Cards & Banks')],
        ];
    }

}
