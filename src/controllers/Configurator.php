<?php

namespace benf\neo\controllers;

use benf\neo\elements\Block;
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
     * Renders field layout designers for pasted or cloned block types.
     *
     * @return Response
     */
    public function actionRenderFieldLayout(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $config = $request->getRequiredBodyParam('layout');
        $fieldLayout = FieldLayout::createFromConfig($config);

        $view = Craft::$app->getView();
        $view->startJsBuffer();
        $html = $view->renderTemplate('_includes/fieldlayoutdesigner', [
            'fieldLayout' => $fieldLayout ?? new FieldLayout(['type' => Block::class]),
            'customizableUi' => true,
        ]);
        $view->clearJsBuffer();

        return $this->asJson([
            'success' => true,
            'html' => $html,
        ]);
    }
}
