<?php
namespace GardenLawn\Core\Model\Newsletter;

use Magento\Newsletter\Model\Subscriber as BaseSubscriber;

class Subscriber extends BaseSubscriber
{
    /**
     * Sends out confirmation success email
     *
     * @return $this
     */
    public function sendConfirmationSuccessEmail()
    {
        $vars = [
            'subscriber_data' => [
                'unsubscription_link' => $this->getUnsubscriptionLink(),
            ],
        ];

        // We cannot call parent::sendEmail because it is private.
        // We have to rely on the fact that sendConfirmationSuccessEmail calls sendEmail.
        // But sendConfirmationSuccessEmail in parent does NOT accept vars.

        // So we have to copy the logic of sendEmail here? No, that's bad.
        // But wait, if we override sendConfirmationSuccessEmail, we can't call the private sendEmail of the parent.

        // This is a problem with Magento's design here.

        // Let's look at the parent class again.
        // private function sendEmail(string $emailTemplatePath, string $emailIdentityPath, array $templateVars = []): void

        // Since it's private, we CANNOT call it from the child class.
        // This means we CANNOT simply override sendConfirmationSuccessEmail and call sendEmail with vars.

        // However, we can use reflection to call the private method, or we have to copy the sendEmail method.
        // Copying is safer for stability but bad for maintenance.
        // Reflection is cleaner but slower.

        // But wait! The user said "link jest pusty" (link is empty).
        // This might be because `getUnsubscriptionLink` returns empty string?
        // Or because `{{var subscriber.getUnsubscriptionLink()}}` is not working in the template.

        // In Magento 2 email templates, calling methods on objects is allowed if the method is public.
        // `getUnsubscriptionLink` is public.

        // Why would it be empty?
        // Maybe `_newsletterData` is not initialized correctly? No, it's injected.
        // Maybe `getStoreId` is missing?

        // Let's check `getUnsubscriptionLink` implementation in `Subscriber.php`:
        // return $this->_newsletterData->getUnsubscribeUrl($this);

        // And `Helper/Data.php`:
        // return $this->_frontendUrlBuilder->setScope($subscriber->getStoreId())->getUrl(...)

        // If `getStoreId()` returns 0 or null, it might be an issue.
        // But usually it should be set.

        // Another possibility: The template engine does not support method calls with arguments (it doesn't have arguments here), or method calls at all in some contexts?
        // Actually, `{{var subscriber.getUnsubscriptionLink()}}` should work.

        // However, if the user says it's empty, maybe I should try to pass it as a variable explicitly.
        // To do that, I MUST override the class because `sendEmail` is private and I can't hook into it easily to add variables.

        // Wait, if I override `sendConfirmationSuccessEmail`, I can't call `sendEmail`.
        // So I MUST copy `sendEmail` to my child class (as protected/private) and use it.
        // This is the only way to pass variables if I can't use the parent's private method.

        // Let's copy `sendEmail` and `sendConfirmationSuccessEmail`.

        $this->sendEmail(
            self::XML_PATH_SUCCESS_EMAIL_TEMPLATE,
            self::XML_PATH_SUCCESS_EMAIL_IDENTITY,
            ['subscriber_data' => ['unsubscription_link' => $this->getUnsubscriptionLink()]]
        );

        return $this;
    }

    /**
     * Send email about change status
     *
     * @param string $emailTemplatePath
     * @param string $emailIdentityPath
     * @param array $templateVars
     * @return void
     */
    protected function sendEmail(string $emailTemplatePath, string $emailIdentityPath, array $templateVars = []): void
    {
        if ($this->getImportMode()) {
            return;
        }

        $template = $this->_scopeConfig->getValue($emailTemplatePath, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->getStoreId());
        $identity = $this->_scopeConfig->getValue($emailIdentityPath, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->getStoreId());
        if (!$template || !$identity) {
            return;
        }

        $templateVars += ['subscriber' => $this];
        $this->inlineTranslation->suspend();
        $this->_transportBuilder->setTemplateIdentifier(
            $template
        )->setTemplateOptions(
            [
                'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                'store' => $this->getStoreId(),
            ]
        )->setTemplateVars(
            $templateVars
        )->setFromByScope(
            $identity,
            $this->getStoreId()
        )->addTo(
            $this->getEmail(),
            $this->getName()
        );
        $transport = $this->_transportBuilder->getTransport();
        $transport->sendMessage();

        $this->inlineTranslation->resume();
    }
}
