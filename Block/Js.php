<?php
declare(strict_types=1);

namespace Nofrixion\Payments\Block;

use Magento\Framework\View\Element\AbstractBlock;

class Js extends AbstractBlock
{

    private \Nofrixion\Payments\Helper\Data $helper;

    public function __construct(\Magento\Framework\View\Element\Context $context, \Nofrixion\Payments\Helper\Data $helper, array $data = [])
    {
        parent::__construct($context, $data);
        $this->helper = $helper;
    }

    protected function _toHtml()
    {
        if ($this->helper->isProductionMode()) {
            $src = 'https://api.nofrixion.com/js/payelement.js';
        } else {
            $src = 'https://api-sandbox.nofrixion.com/js/payelement.js';
        }
        return '<script src="' . $src . '"></script>';
    }
}
