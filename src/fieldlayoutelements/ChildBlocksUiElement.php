<?php

namespace benf\neo\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\BaseUiElement;

/**
 * A field layout UI element for Neo child blocks.
 * 
 * @package benf\neo\fieldlayoutelements
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.10.0
 */
class ChildBlocksUiElement extends BaseUiElement
{
    /**
     * @inheritdoc
     */
    protected function selectorLabel(): string
    {
        return Craft::t('neo', 'Child Blocks');
    }

    /**
     * @inheritdoc
     */
    public function formHtml(ElementInterface $element = null, bool $static = false)
    {
        return Craft::$app->getView()->renderTemplate('neo/child-blocks', []);
    }
}