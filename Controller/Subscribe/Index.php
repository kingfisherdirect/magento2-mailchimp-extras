<?php

namespace KingfisherDirect\MailchimpExtras\Controller\Subscribe;

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
        $storeId = $this->storeManager->getStore()->getId();

        $api = $this->mailchimpData->getApi($storeId);
        $listId = $this->mailchimpData->getDefaultList();

        /** @var \Mailchimp_ListsMembers **/
        $members = $api->lists->members;

        $resultPage = $this->pageFactory->create();
        $block = $resultPage->getLayout()->getBlock('newsletter.preferences_form');

        if ($this->getRequest()->isPost()) {
            if (!$this->formKeyValidator->validate($this->getRequest())) {
                $this->messageManager->addWarning(__("Invalid form security key. Please try again"));
            } else {
                $errors = [];

                $post = $this->getRequest()->getParam("member", []);

                $mergeFields = is_array($post['data']) ? $post['data'] : null;

                $post['interest'] = $post['interest'] ?? [];

                if (is_array($post['interest'])) {
                    $interests = $this->getInterests();

                    foreach ($interests as $interest => $isInterested) {
                        $interests[$interest] = in_array($interest, $post['interest']);
                    }
                }

                if (!$post['email'] || !filter_var($post['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = [__("Please enter valid email address")];
                }

                if (empty($errors)) {
                    try {
                        $saved = $members->add($listId, "subscribed", $post['email'], null, $mergeFields, $interests);
                        $this->messageManager->addSuccess(__("You have been successfully subscribed to newsletter!"));

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
            }
        }

        return $resultPage;
    }

    private function getStoreId(): int
    {
        return $this->storeManager->getStore()->getId();
    }

    private function getInterests(): array
    {
        $interests = $this->mailchimpData->getInterest($this->getStoreId());
        $ids = [];

        foreach ($interests as $interestGroup) {
            foreach ($interestGroup['category'] as $category) {
                $ids[$category['id']] = false;
            }
        }

        return $ids;
    }
}
