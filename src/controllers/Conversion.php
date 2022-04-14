<?php

namespace benf\neo\controllers;

use benf\neo\Plugin as Neo;
use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Controller for handling conversion of Neo fields to Matrix.
 *
 * @package benf\neo\controllers
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.2.0
 */
class Conversion extends Controller
{
    /**
     * Converts a Neo field to a Matrix field.
     *
     * @return Response
     * @throws \Throwable
     */
    public function actionConvertToMatrix(): Response
    {
        $this->requireAdmin();
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $fieldId = Craft::$app->getRequest()->getParam('fieldId');
        $neoField = Craft::$app->getFields()->getFieldById($fieldId);

        $return = [];

        try {
            $return['success'] = Neo::$plugin->conversion->convertFieldToMatrix($neoField);
        } catch (\Throwable $e) {
            $return['success'] = false;
            $return['errors'] = [$e->getMessage()];
        }

        return $this->asJson($return);
    }
}
