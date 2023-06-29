<?php
declare(strict_types=1);

namespace NoFrixion\Payments\Model\Config\Source;

class Mode implements \Magento\Framework\Data\OptionSourceInterface
{

    public function toOptionArray()
    {
        return [['value' => '1', 'label' => __('Production Mode')], ['value' => '0', 'label' => __('Sandbox Mode')]];
    }

}
