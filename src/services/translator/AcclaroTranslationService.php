<?php
/**
 * Translations for Craft plugin for Craft CMS 3.x
 *
 * Translations for Craft eliminates error prone and costly copy/paste workflows for launching human translated Craft CMS web content.
 *
 * @link      http://www.acclaro.com/
 * @copyright Copyright (c) 2018 Acclaro
 */

namespace acclaro\translations\services\translator;

use Craft;
use DateTime;
use Exception;
use craft\elements\Entry;
use craft\elements\Category;
use craft\elements\GlobalSet;
use acclaro\translations\services\App;
use acclaro\translations\elements\Order;
use acclaro\translations\models\FileModel;
use acclaro\translations\Translations;
use acclaro\translations\models\CategoryDraftModel;
use acclaro\translations\services\api\AcclaroApiClient;
use acclaro\translations\services\job\acclaro\SendOrder;
use acclaro\translations\services\job\acclaro\UdpateReviewFileUrls;

class AcclaroTranslationService implements TranslationServiceInterface
{
    /**
     * @var boolean
     */
    protected $sandboxMode = false;
    
    /**
     * @var acclaro\translations\services\api\AcclaroApiClient
     */
    protected $acclaroApiClient;
    
    /**
     * @var array
     */
    protected $settings;

    /**
     * @param array                                                         $settings
     * @param acclaro\translations\services\api\AcclaroApiClient    $acclaroApiClient
     */
    public function __construct(
        array $settings,
        AcclaroApiClient $acclaroApiClient
    ) {
        if (!isset($settings['apiToken'])) {
            throw new Exception('Missing apiToken');
        }

        $this->settings = $settings;

        $this->sandboxMode = !empty($settings['sandboxMode']);

        $this->acclaroApiClient = $acclaroApiClient ?: new AcclaroApiClient(
            $settings['apiToken'],
            !empty($settings['sandboxMode'])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate()
    {
        $response = $this->acclaroApiClient->getAccount();

        return !empty($response->plunetid);
    }

    /**
     * {@inheritdoc}
     */
    public function updateOrder(Order $order)
    {
        $orderResponse = $this->acclaroApiClient->getOrder($order->serviceOrderId);

        if (empty($orderResponse->status)) {
            return;
        }

        if ($order->status !== $orderResponse->status) {
            $order->logActivity(
                sprintf(Translations::$plugin->translator->translate('app', 'Order status changed to %s'), $orderResponse->status)
            );
        }
        
        if ($order->title !== $orderResponse->name) {
            Translations::$plugin->orderRepository->saveOrderName($order->id, $orderResponse->name);
        }
        
        $order->status = $orderResponse->status;
    }

    /**
     * {@inheritdoc}
     */
    public function updateFile(Order $order, FileModel $file)
    {
        try {
            $fileInfoResponse = $this->acclaroApiClient->getFileInfo($order->serviceOrderId);
            
            if (!is_array($fileInfoResponse)) {
                return;
            }
            // find the matching file
            foreach ($fileInfoResponse as $fileInfo) {
                if ($fileInfo->fileid == $file->serviceFileId) {
                    break;
                }
    
                $fileInfo = null;
            }
    
            if (empty($fileInfo->targetfile)) {
                return;
            }
    
            $targetFileId = $fileInfo->targetfile;
    
            $fileStatusResponse = $this->acclaroApiClient->getFileStatus($order->serviceOrderId, $targetFileId);
    
            $file->status = $fileStatusResponse->status;
            $file->dateDelivered = new \DateTime();
    
            // download the file
            $target = $this->acclaroApiClient->getFile($order->serviceOrderId, $targetFileId);
    
            if ($target) {
                $file->target = $target;
    
                $element = Craft::$app->elements->getElementById($file->elementId, null, $file->sourceSite);
    
                if ($element instanceof GlobalSet) {
                    $draft = Translations::$plugin->globalSetDraftRepository->getDraftById($file->draftId, $file->targetSite);
                } else if ($element instanceof Category) {
                    $draft = Translations::$plugin->categoryDraftRepository->getDraftById($file->draftId, $file->targetSite);
    
                    $category = Craft::$app->getCategories()->getCategoryById($draft->categoryId, $draft->site);
                    $draft->groupId = $category->groupId;
                } else {
                    $draft = Translations::$plugin->draftRepository->getDraftById($file->draftId, $file->targetSite);
                }
    
                $this->updateDraftFromXml($element, $draft, $target, $file->sourceSite, $file->targetSite, $order);
            }
        } catch (Exception $e) {
            Craft::error(  '['. __METHOD__ .'] Couldn’t update file. Error: '.$e->getMessage(), 'translations' );
        }
    }

    public function updateDraftFromXml($element, $draft, $xml, $sourceSite, $targetSite, $order)
    {
        $targetData = Translations::$plugin->elementTranslator->getTargetDataFromXml($xml);

        switch (true) {
            // Update Entry Drafts
            case $draft instanceof Entry:
                $draft->title = isset($targetData['title']) ? $targetData['title'] : $draft->title;
                $draft->slug = isset($targetData['slug']) ? $targetData['slug'] : $draft->slug;
                
                $post = Translations::$plugin->elementTranslator->toPostArrayFromTranslationTarget($draft, $sourceSite, $targetSite, $targetData);
                
                $draft->setFieldValues($post);
                
                $draft->siteId = $targetSite;
                
                $res = Translations::$plugin->draftRepository->saveDraft($draft);
                if ($res !== true) {
                    if(is_array($res)){
                        $errorMessage = '';
                        foreach ($res as $r){
                            $errorMessage .= implode('; ', $r);
                        }
                        $order->logActivity(
                            sprintf(Translations::$plugin->translator->translate('app', 'Error: '.$errorMessage))
                        );
                    } else {
                        $order->logActivity(
                            sprintf(Translations::$plugin->translator->translate('app', 'Unable to save draft.'))
                        );
                    }

                    return false;
                }
                break;
            
            // Update Category Drafts
            case $draft instanceof Category:
                $draft->title = isset($targetData['title']) ? $targetData['title'] : $draft->title;
                $draft->slug = isset($targetData['slug']) ? $targetData['slug'] : $draft->slug;
                $draft->siteId = $targetSite;
                
                $post = Translations::$plugin->elementTranslator->toPostArrayFromTranslationTarget($element, $sourceSite, $targetSite, $targetData);

                $draft->setFieldValues($post);

                $res = Translations::$plugin->categoryDraftRepository->saveDraft($draft, $post);
                if (!$res) {
                    $order->logActivity(
                        sprintf(Translations::$plugin->translator->translate('app', 'Unable to save draft, please review your XML file %s'), $file_name)
                    );

                    return false;
                }
                break;
            
            // Update GlobalSet Drafts
            case $draft instanceof GlobalSet:
                $draft->siteId = $targetSite;
               
                // $element->siteId = $targetSite;
                $post = Translations::$plugin->elementTranslator->toPostArrayFromTranslationTarget($element, $sourceSite, $targetSite, $targetData);

                $draft->setFieldValues($post);

                $res = Translations::$plugin->globalSetDraftRepository->saveDraft($draft, $post);
                if (!$res) {
                    $order->logActivity(
                        sprintf(Translations::$plugin->translator->translate('app', 'Unable to save draft, please review your XML file %s'), $file_name)
                    );

                    return false;
                }
                break;
            default:
                break;
        }
    }
 
    /**
     * {@inheritdoc}
     */
    public function sendOrder(Order $order)
    {
        $job = Craft::$app->queue->push(new SendOrder([
            'description' => 'Sending order to Acclaro',
            'order' => $order,
            'sandboxMode' => $this->sandboxMode,
            'settings' => $this->settings
        ]));
    }
    
    /**
     * {@inheritdoc}
     */
    public function udpateReviewFileUrls(Order $order)
    {
        $job = Craft::$app->queue->push(new UdpateReviewFileUrls([
            'description' => 'Updating Acclaro review urls',
            'order' => $order,
            'sandboxMode' => $this->sandboxMode,
            'settings' => $this->settings
        ]));
    }

    public function getOrderUrl(Order $order)
    {
        $subdomain = $this->sandboxMode ? 'apisandbox' : 'my';

        return sprintf('https://%s.acclaro.com/portal/vieworder.php?id=%s', $subdomain, $order->serviceOrderId);
    }

    public function getLanguages()
    {
        return $this->acclaroApiClient->getLanguages();
    }
    
    public function getLanguagePairs($source)
    {
        return $this->acclaroApiClient->getLanguagePairs($source);
    }

    public function editOrderName($orderId, $name)
    {
        return $this->acclaroApiClient->editOrderName($orderId, $name);
    }

    public function sendOrderFile($order, $file, $settings) {

        $tempPath = Craft::$app->path->getTempPath();
        $acclaroApiClient = new AcclaroApiClient(
            $settings['apiToken'],
            !empty($settings['sandboxMode'])
        );

        if ($file) {

            $element = Craft::$app->elements->getElementById($file->elementId, null, $file->sourceSite);

            $sourceSite = Translations::$plugin->siteRepository->normalizeLanguage(Craft::$app->getSites()->getSiteById($file->sourceSite)->language);
            $targetSite = Translations::$plugin->siteRepository->normalizeLanguage(Craft::$app->getSites()->getSiteById($file->targetSite)->language);

            if ($element instanceof GlobalSetModel) {
                $filename = ElementHelper::normalizeSlug($element->name).'-'.$targetSite.'.xml';
            } else {
                $filename = $element->slug.'-'.$targetSite.'.xml';
            }

            $path = $tempPath .'/'. $file->elementId .'-'. $filename;

            $stream = fopen($path, 'w+');

            fwrite($stream, $file->source);

            $fileResponse = $acclaroApiClient->sendSourceFile(
                $order->serviceOrderId,
                $sourceSite,
                $targetSite,
                $file->id,
                $path
            );

            $file->serviceFileId = $fileResponse->fileid ? $fileResponse->fileid : $file->id;
            $file->status = $fileResponse->status;

            $fileCallbackResponse = $acclaroApiClient->requestFileCallback(
                $order->serviceOrderId,
                $file->serviceFileId,
                Translations::$plugin->urlGenerator->generateFileCallbackUrl($file)
            );

            $acclaroApiClient->addReviewUrl(
                $order->serviceOrderId,
                $file->serviceFileId,
                $file->previewUrl
            );

            Translations::$plugin->fileRepository->saveFile($file);

            fclose($stream);

            unlink($path);
        }

    }
}
