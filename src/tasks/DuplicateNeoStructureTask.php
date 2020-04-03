<?php
namespace benf\neo\tasks;

use Craft;
use craft\queue\BaseJob;
use benf\neo\Plugin as Neo;
use benf\neo\models\BlockStructure;

class DuplicateNeoStructureTask extends BaseJob
{
    public $field;
    
    public $owner;
    
    public $blocks;
    
    public $siteId;
    
    public function execute($queue)
    {
        $siteId = $this->siteId ?? $this->owner->siteId;
        // Delete any existing block structures associated with this field/owner/site combination
        while (($blockStructure = Neo::$plugin->blocks->getStructure($this->field->id, $this->owner->id, $siteId)) !== null)
        {
            Neo::$plugin->blocks->deleteStructure($blockStructure);
        }
        $this->setProgress($queue, 0.3);
        
        if (Craft::$app->getElements()->getElementById($this->owner->id)) {
            $blockStructure = new BlockStructure();
            $blockStructure->fieldId = (int)$this->field->id;
            $blockStructure->ownerId = (int)$this->owner->id;
            $blockStructure->ownerSiteId = (int)$siteId;
            $this->setProgress($queue, 0.6);
            
            Neo::$plugin->blocks->saveStructure($blockStructure);
            Neo::$plugin->blocks->buildStructure($this->blocks, $blockStructure);
        }
        $this->setProgress($queue, 1);
    }
    
    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('app', 'Saving the neo structure for duplicated elements');
    }
}
