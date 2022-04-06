<?php

namespace KingfisherDirect\MailchimpExtras\Controller\Preferences;

use Ebizmarts\MailChimp\Helper\Data;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

class AddInterest extends Action
{
    private Data $mailchimpData;

    private Context $context;

    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        Data $mailchimpData,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);

        $this->context = $context;
        $this->mailchimpData = $mailchimpData;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $member = $params['memberId'] ?? null;
        $interest = $params['interestId'] ?? null;

        $storeId = $this->storeManager->getStore()->getId();

        $api = $this->mailchimpData->getApi($storeId);
        $listId = $this->mailchimpData->getDefaultList();

        if (!$member || !$interest) {
            $this->messageManager->addWarning(__("This link is incorrect. You can subscribe from this page"));

            return $this->_redirect("newsletter/preferences");
        }

        /** @var \Mailchimp_ListsMembers **/
        $members = $api->lists->members;
        $search = $members->getAll($listId, null, null, 1, null, null, null, null, null, null, null, $member);
        $member = count($search['members']) === 1 ? $search['members'][0] : null;

        if (!$member) {
            $this->messageManager->addWarning(__("Subscriber information was not found. You can subscribe from this page"));

            return $this->_redirect("newsletter/preferences");
        }

        $interests = $member['interests'];

        if (!isset($interests[$interest])) {
            $this->messageManager->addWarning(__("Failed to update interests. You can do it from this page"));

            return $this->_redirect("newsletter/preferences", ["memberId" => $member["unique_email_id"]]);
        }

        $interests[$interest] = true;

        try {
            $update = $members->update($listId, $member['id'], null, null, null, $interests);
        } catch (\Exception $e) {
            $this->messageManager->addError(__("Something went wrong while saving your information."));

            return $this->_redirect("newsletter/preferences", ["memberId" => $member["unique_email_id"]]);
        }

        $this->messageManager->addSuccess(__("Your preference was updated. You can add more interests on this page"));

        return $this->_redirect("newsletter/preferences", ["memberId" => $member["unique_email_id"]]);
    }
}
