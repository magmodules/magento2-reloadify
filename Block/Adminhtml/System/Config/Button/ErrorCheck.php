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
 * Error log check button class
 *
 */
class ErrorCheck extends Field
{

    /**
     * @var string
     */
    protected $_template = 'Magmodules_Reloadify::system/config/button/error.phtml';

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
    public function getErrorCheckUrl()
    {
        return $this->getUrl('reloadify/log/error');
    }

    /**
     * @return mixed
     */
    public function getButtonHtml()
    {
        $buttonData = ['id' => 'magmodules-reloadify-button_error', 'label' => __('Check last 100 error log records')];
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
