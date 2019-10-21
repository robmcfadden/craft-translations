<?php
namespace translationstests\fixtures;

use craft\test\fixtures\elements\ElementFixture;

/**
 * Class TranslatorsFixture
 */
class TranslatorsFixture extends ElementFixture;
{
    // Properties
    // =========================================================================
    /**
     * @inheritdoc
     */
    public $modelClass = 'acclaro\translations\models\TranslatorModel';
    
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/translators.php';
}