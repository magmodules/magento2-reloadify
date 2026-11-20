<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Block\Adminhtml\System\Config\Button;

use Exception;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Debug log check button class
 */
class DebugCheck extends Field
{

    /**
     * @var string
     */
    protected $_template = 'Magmodules_Reloadify::system/config/button/debug.phtml';

    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    public function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * @return string
     */
    public function getDebugCheckUrl()
    {
        return $this->getUrl('reloadify/log/debug');
    }

    /**
     * @return mixed
     */
    public function getButtonHtml()
    {
        $buttonData = ['id' => 'magmodules-reloadify-button_debug', 'label' => __('Check last 100 debug log records')];
        try {
            return $this->getLayout()->createBlock(
                Button::class,
                '',
                ['data' => $buttonData]
            )->toHtml();
        } catch (Exception $e) {
            return false;
        }
    }
}
