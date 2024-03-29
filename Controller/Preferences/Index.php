<?php

namespace KingfisherDirect\MailchimpExtras\Controller\Preferences;

use Ebizmarts\MailChimp\Helper\Data;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Index extends Action
{
    private PageFactory $pageFactory;

    private Data $mailchimpData;

    private Context $context;

    private StoreManagerInterface $storeManager;

    private Validator $formKeyValidator;

    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        Data $mailchimpData,
        StoreManagerInterface $storeManager,
        Validator $formKeyValidator,
        LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->context = $context;
        $this->pageFactory = $pageFactory;
        $this->mailchimpData = $mailchimpData;
        $this->storeManager = $storeManager;
        $this->formKeyValidator = $formKeyValidator;
        $this->logger = $logger;
    }

    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $member = $params['memberId'] ?? null;

        $storeId = $this->getStoreId();

        $api = $this->mailchimpData->getApi($storeId);
        $listId = $this->mailchimpData->getDefaultList();

        /** @var \Mailchimp_ListsMembers **/
        $members = $api->lists->members;

        if ($member) {
            $search = $members->getAll($listId, null, null, 1, null, null, null, null, null, null, null, $member);

            $member = count($search['members']) === 1 ? $search['members'][0] : null;
        }

        if (!$member) {
            $this->messageManager->addWarning(__("We were not able to find your details, but you can subscribe from this page."));

            return $this->_redirect("newsletter/subscribe");
        }

        $resultPage = $this->pageFactory->create();
        $block = $resultPage->getLayout()->getBlock('newsletter.preferences_form');

        if ($block) {
            $block->setMember($member);
        }

        if (!$this->getRequest()->isPost()) {
            return $resultPage;
        }

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addWarning(__("Invalid form security key. Please try again"));

            return $resultPage;
        }


        $errors = [];

        $post = $this->getRequest()->getParam("member", []);

        $post['interest'] = $post['interest'] ?? [];

        if (is_array($post['interest'])) {
            $interests = $member['interests'] ?? [];

            foreach ($interests as $interest => $isInterested) {
                $interests[$interest] = in_array($interest, $post['interest']);
            }
        }

        $postMergeFields = is_array($post['data']) ? $post['data'] : null;

        foreach ($this->getMergeFields() as $mergeField) {
            $tag = $mergeField["tag"];

            if ($mergeField['public'] === false) {
                if (array_key_exists($tag, $postMergeFields)) {
                    unset($postMergeFields[$tag]);
                }

                continue;
            }

            if ($mergeField['required'] === false) {
                continue;
            }

            if (empty($postMergeFields[$tag])) {
                $errors["data[{$tag}]"] = [__("This field is required")];
            }
        }

        if (empty($errors)) {
            try {
                $saved = $members->update($listId, $member['id'], null, "subscribed", $postMergeFields, $interests);

                $this->messageManager->addSuccess(__("Your preferences were successfully saved!"));

                return $this->_redirect("newsletter/preferences", ["memberId" => $saved["unique_email_id"]]);
            } catch (\Exception $e) {
                $this->messageManager->addError(__("Something went wrong while saving your information."));
                $this->logger->error($e->getMessage());
            }
        } else {
            $this->messageManager->addError(__("Please correct errors in form and try again"));
        }

        $block->setPostData($post);
        $block->setErrors($errors);

        return $resultPage;
    }

    private function getStoreId(): int
    {
        return $this->storeManager->getStore()->getId();
    }

    private function getMergeFields(): array
    {
        $api = $this->mailchimpData->getApi($this->getStoreId());
        $listId = $this->mailchimpData->getDefaultList();
        /** @var \Mailchimp_ListsMergeFields **/
        $mergeApi = $api->lists->mergeFields;

        $mergeFields = $mergeApi->getAll($listId, "merge_fields")["merge_fields"];

        return $mergeFields;
    }
}
