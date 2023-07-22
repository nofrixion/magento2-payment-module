<?php
declare(strict_types=1);

namespace Nofrixion\Payments\Model\Config\Source;

class PispCurrency implements \Magento\Framework\Data\OptionSourceInterface
{

    public function toOptionArray()
    {
        return [
            ['value' => 'EUR', 'label' => 'Euro'],
            ['value' => 'GBP', 'label' => 'GBP'],
        ];
    }

}