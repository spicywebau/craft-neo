<?php

namespace benf\neo\controllers;

use benf\neo\elements\Block;
use benf\neo\Field;
use benf\neo\models\BlockType;
use benf\neo\Plugin as Neo;
use Craft;
use craft\models\FieldLayout;
use craft\web\Controller;
use yii\web\Response;

/**
 * Class Configurator
 *
 * @package benf\neo\controllers
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.0.0
 */
class Configurator extends Controller
{
    /**
     * Renders settings and field layout designers for block types.
     *
     * @return Response
     * @since 3.6.0
     */
    public function actionRenderBlockType(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $renderedData = $this->_render();

        return $this->asJson([
            'success' => true,
            'settingsHtml' => $renderedData['settingsHtml'],
            'settingsJs' => $renderedData['settingsJs'],
            'layoutHtml' => $renderedData['layoutHtml'],
        ]);
    }

    /**
     * Renders field layout designers for block types.
     *
     * @return Response
     * @since 3.1.0
     */
    public function actionRenderFieldLayout(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        return $this->asJson([
            'success' => true,
            'html' => $this->_render()['layoutHtml'],
        ]);
    }

    private function _render(): array
    {
        $request = Craft::$app->getRequest();
        $settings = $request->getBodyParam('settings');
        $layoutId = $request->getBodyParam('layoutId');
        $layoutConfig = $request->getBodyParam('layout');

        // Prioritise the config
        if ($layoutConfig) {
            $fieldLayout = FieldLayout::createFromConfig($layoutConfig);
            $fieldLayout->type = Block::class;
        } else {
            $fieldLayout = $layoutId
                ? Craft::$app->getFields()->getLayoutById($layoutId)
                : new FieldLayout(['type' => Block::class]);
        }

        if ($settings) {
            $newBlockType = new BlockType();
            $newBlockType->name = $settings['name'];
            $newBlockType->handle = $settings['handle'];
            $newBlockType->enabled = $settings['enabled'];
            $newBlockType->ignorePermissions = $settings['ignorePermissions'];
            $newBlockType->description = $settings['description'] ?? '';
            $newBlockType->iconId = $settings['iconId'];
            $newBlockType->minBlocks = (int)$settings['minBlocks'];
            $newBlockType->maxBlocks = (int)$settings['maxBlocks'];
            $newBlockType->minSiblingBlocks = (int)$settings['minSiblingBlocks'];
            $newBlockType->maxSiblingBlocks = (int)$settings['maxSiblingBlocks'];
            $newBlockType->minChildBlocks = (int)$settings['minChildBlocks'];
            $newBlockType->maxChildBlocks = (int)$settings['maxChildBlocks'];
            $newBlockType->topLevel = (bool)$settings['topLevel'];
            $newBlockType->groupChildBlockTypes = (bool)($settings['groupChildBlockTypes'] ?? true);
            $newBlockType->childBlocks = $settings['childBlocks'] ?: null;
            $newBlockType->sortOrder = (int)$settings['sortOrder'];
            $newBlockType->conditions = $settings['conditions'] ?? [];
            [$settingsHtml, $settingsJs] = Neo::$plugin->blockTypes->renderBlockTypeSettings(
                $newBlockType,
                'types[' . Field::class . ']',
            );
        } else {
            $settingsHtml = null;
            $settingsJs = null;
        }

        return [
            'settingsHtml' => $settingsHtml,
            'settingsJs' => $settingsJs,
            'layoutHtml' => Neo::$plugin->blockTypes->renderFieldLayoutDesigner($fieldLayout),
        ];
    }
}
