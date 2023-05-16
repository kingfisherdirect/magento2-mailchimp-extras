<?php

namespace KingfisherDirect\MailchimpExtras\Block;

use Ebizmarts\MailChimp\Helper\Data;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class PreferencesForm extends Template
{
    private Data $mailchimpData;

    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        Data $mailchimpData,
        array $data = [],
    ) {
        parent::__construct($context, $data);

        $this->storeManager = $context->getStoreManager();
        $this->mailchimpData = $mailchimpData;
    }

    public function getStoreId(): int
    {
        return $this->storeManager->getStore()->getId();
    }

    public function getInterest(): array
    {
        return $this->mailchimpData->getInterest($this->getStoreId());
    }

    public function getMergeFields(): array
    {
        $api = $this->mailchimpData->getApi($this->getStoreId());
        $listId = $this->mailchimpData->getDefaultList();
        /** @var \Mailchimp_ListsMergeFields **/
        $mergeApi = $api->lists->mergeFields;

        $mergeFields = $mergeApi->getAll($listId, "merge_fields")["merge_fields"];
        usort($mergeFields, function ($a, $b) {
            return $a['display_order'] - $b['display_order'];
        });

        return $mergeFields;
    }
}
