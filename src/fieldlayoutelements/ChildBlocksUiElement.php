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
    protected function selectorIcon(): ?string
    {
        return '@benf/neo/icon.svg';
    }

    /**
     * @inheritdoc
     */
    public function formHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        $blockId = $element && $element->id ? $element->id : '__NEOBLOCK__';

        // This will be replaced in `block.twig` and `benf\neo\services\Blocks::renderTabs()`; done like this so we can
        // use `craft\models\FieldLayout::createForm()` without repeatedly namespacing the child blocks
        return '<div data-neo-child-blocks-ui-element="' . $blockId . '"></div>';
    }
}
