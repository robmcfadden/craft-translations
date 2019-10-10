<?php

namespace translationstest\unit\tests;

use Codeception\Test\Unit;

use UnitTester;
use Craft;

use translationstest\fixtures\TranslatorFixture;

use acclaro\translations\Translations;
use acclaro\translations\services\App;
use acclaro\translations\models\TranslatorModel;
use acclaro\translations\records\TranslatorRecord;
use acclaro\translations\services\translator\AcclaroTranslationService;
use acclaro\translations\services\translator\Export_ImportTranslationService;

class TranslatorTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var TranslatorModel
     */
    protected $localTranslator;
    
    /**
     * @var TranslatorModel
     */
    protected $acclaroTranslator;

    public function testCreateLocalTranslator()
    {
        $this->assertSame(
            true,
            Translations::$plugin->translatorRepository->saveTranslator($this->localTranslator)
        );
    }
    
    public function testCreateAcclaroTranslator()
    {
        $this->assertSame(
            true,
            Translations::$plugin->translatorRepository->saveTranslator($this->acclaroTranslator)
        );
    }
    
    protected function _before()
    {
        parent::_before();

        $this->localTranslator = Translations::$plugin->translatorRepository->makeNewTranslator();
        $this->localTranslator->label = 'Local';
        $this->localTranslator->service = 'export_import';
        $this->localTranslator->status = 'active';
        $this->localTranslator->settings = [];
        
        $this->acclaroTranslator = Translations::$plugin->translatorRepository->makeNewTranslator();
        $this->acclaroTranslator->label = 'Acclaro';
        $this->acclaroTranslator->service = 'acclaro';
        $this->acclaroTranslator->status = 'active';
        $this->acclaroTranslator->settings = [
            'apiToken' => '123456',
            'sandboxMode' => true
        ];
    }
}

