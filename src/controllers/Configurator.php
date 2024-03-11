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

        $renderedData = $this->_renderBlockType();

        return $this->asJson([
            'success' => true,
            'settingsHtml' => $renderedData['settingsHtml'],
            'settingsJs' => $renderedData['settingsJs'],
            'bodyHtml' => $renderedData['bodyHtml'],
            'headHtml' => $renderedData['headHtml'],
            'layoutHtml' => $renderedData['layoutHtml'],
        ]);
    }

    /**
     * Renders settings for block type groups.
     *
     * @return Response
     * @since 3.8.0
     */
    public function actionRenderBlockTypeGroup(): Response
    {
        $request = Craft::$app->getRequest();
        $groupId = $request->getBodyParam('groupId');
        $group = $groupId ? Neo::$plugin->blockTypes->getGroupById((int)$groupId) : null;
        [$html, $js] = Neo::$plugin->blockTypes->renderBlockTypeGroupSettings(
            $group,
            'types[' . Field::class . ']',
        );

        return $this->asJson([
            'success' => true,
            'settingsHtml' => $html,
            'settingsJs' => $js,
        ]);
    }

    private function _renderBlockType(): array
    {
        $request = Craft::$app->getRequest();
        $blockTypeId = $request->getBodyParam('blockTypeId');
        $settings = $request->getBodyParam('settings');
        $errors = $request->getBodyParam('errors', []);
        $layoutConfig = $request->getBodyParam('layout');
        $blockType = $blockTypeId ? Neo::$plugin->blockTypes->getById((int)$blockTypeId) : null;

        // Prioritise the config
        if ($layoutConfig) {
            $fieldLayout = FieldLayout::createFromConfig($layoutConfig);
            $fieldLayout->type = Block::class;
        } else {
            $fieldLayout = $blockType?->getFieldLayout() ?? new FieldLayout(['type' => Block::class]);
        }

        if ($blockType) {
            // Apply errors before rendering settings
            foreach ($errors as $attr => $attrErrors) {
                foreach ($attrErrors as $attrError) {
                    $blockType->addError($attr, $attrError);
                }
            }

            $renderedSettings = Neo::$plugin->blockTypes->renderSettings(
                $blockType,
                'types[' . str_replace('\\', '-', Field::class) . ']',
            );
        } else {
            $newBlockType = new BlockType();

            if ($settings) {
                $newBlockType->name = $settings['name'];
                $newBlockType->handle = $settings['handle'];
                $newBlockType->enabled = $settings['enabled'];
                $newBlockType->ignorePermissions = $settings['ignorePermissions'];
                $newBlockType->description = $settings['description'] ?? '';
                $newBlockType->iconFilename = $settings['iconFilename'] ?? '';
                $newBlockType->iconId = $settings['iconId'] ?? null;
                $newBlockType->color = $settings['color'] ?? null;
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
            }

            $renderedSettings = Neo::$plugin->blockTypes->renderSettings(
                $newBlockType,
                'types[' . Field::class . ']',
            );
        }

        return [
            'settingsHtml' => $renderedSettings['settingsHtml'],
            'settingsJs' => $renderedSettings['settingsJs'],
            'bodyHtml' => $renderedSettings['bodyHtml'],
            'headHtml' => $renderedSettings['headHtml'],
            'layoutHtml' => Neo::$plugin->blockTypes->renderFieldLayoutDesigner($fieldLayout),
        ];
    }
}
