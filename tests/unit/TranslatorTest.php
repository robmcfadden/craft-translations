<?php

namespace translationstest\unit;

use Codeception\Test\Unit;

use UnitTester;
use Craft;

use translationstests\fixtures\TranslatorsFixture;

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


    public function _fixtures() : array
    {
        return [
            'translators' => [
                'class' => TranslatorsFixture::class,
                // 'dataFile' => codecept_data_dir() . 'translators.php'
            ],
        ];
    }

    public function testMakeNewTranslators()
    {
        $this->assertSame('Local', $this->localTranslator->label);
        $this->assertSame('export_import', $this->localTranslator->service);
        $this->assertSame('active', $this->localTranslator->status);
        
        // $this->assertSame('Acclaro', $this->acclaroTranslator->label);
        // $this->assertSame('acclaro', $this->acclaroTranslator->service);
        // $this->assertSame('active', $this->acclaroTranslator->status);
        // $this->assertSame('123456', $this->acclaroTranslator->settings->apiToken);
        // $this->assertSame(true, $this->acclaroTranslator->settings->sandboxMode);
    }
    
    public function testSaveTranslators()
    {
        // $this->assertSame(
        //     true,
        //     Translations::$plugin->translatorRepository->saveTranslator($this->localTranslator)
        // );
        
        // $this->assertSame(
        //     true,
        //     Translations::$plugin->translatorRepository->saveTranslator($this->acclaroTranslator)
        // );
    }
    
    public function testDeleteTranslators()
    {
        // $this->assertSame(
        //     true,
        //     Translations::$plugin->translatorRepository->deleteTranslator($this->localTranslator)
        // );
        
        // $this->assertSame(
        //     true,
        //     Translations::$plugin->translatorRepository->deleteTranslator($this->acclaroTranslator)
        // );
    }
    
    protected function _before()
    {
        parent::_before();

        $this->localTranslator = Translations::$plugin->translatorRepository->makeNewTranslator();
        $this->localTranslator->label = 'Local';
        $this->localTranslator->service = 'export_import';
        $this->localTranslator->status = 'active';
        $this->localTranslator->settings = [];
        
        // $this->acclaroTranslator = Translations::$plugin->translatorRepository->makeNewTranslator();
        // $this->acclaroTranslator->label = 'Acclaro';
        // $this->acclaroTranslator->service = 'acclaro';
        // $this->acclaroTranslator->status = 'active';
        // $this->acclaroTranslator->settings = [
        //     'apiToken' => '123456',
        //     'sandboxMode' => true
        // ];

        Translations::$plugin->translatorRepository->saveTranslator($this->localTranslator);
        // Translations::$plugin->translatorRepository->saveTranslator($this->acclaroTranslator);

        $this->localTranslator = Translations::$plugin->translatorRepository->getTranslatorById(1);
        // $this->acclaroTranslator = Translations::$plugin->translatorRepository->getTranslatorById(2);
    }
}

