<?php
namespace translationstests\fixtures;
/**
 * Class TranslatorsFixture
 */
class TranslatorsFixture
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