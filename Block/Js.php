<?php

declare(strict_types=1);

namespace NoFrixion\Payments\Block;

use Magento\Framework\View\Element\AbstractBlock;

class Js extends AbstractBlock
{

    private \NoFrixion\Payments\Helper\Data $helper;

    public function __construct(\Magento\Framework\View\Element\Context $context, \NoFrixion\Payments\Helper\Data $helper, array $data = [])
    {
        parent::__construct($context, $data);
        $this->helper = $helper;
    }

    protected function _toHtml()
    {
        if ($this->helper->isProductionMode()) {
            $src = 'https://api.nofrixion.com/js/payelement.js';
        } else {
            $src = 'https://devnofrixion.azureedge.net/nofrixion.js';
        }
        return '<script>var NOFRIXION_JS = "' . $src . '";</script>';
    }
}
