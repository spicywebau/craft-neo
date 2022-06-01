<?php

namespace benf\neo\controllers;

use benf\neo\elements\Block;
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
     * Renders field layout designers for block types.
     *
     * @return Response
     */
    public function actionRenderFieldLayout(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $id = $request->getBodyParam('layoutId');
        $config = $request->getBodyParam('layout');

        // Prioritise the config
        $fieldLayout = $config
            ? FieldLayout::createFromConfig($config)
            : ($id ? Craft::$app->getFields()->getLayoutById($id) : new FieldLayout(['type' => Block::class]));
        $html = Neo::$plugin->blockTypes->renderFieldLayoutDesigner($fieldLayout);

        return $this->asJson([
            'success' => true,
            'html' => $html,
        ]);
    }
}
