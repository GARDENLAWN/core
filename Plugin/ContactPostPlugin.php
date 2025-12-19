<?php
/**
 * Copyright © GardenLawn. All rights reserved.
 */
declare(strict_types=1);

namespace GardenLawn\Core\Plugin;

use Magento\Contact\Controller\Index\Post;
use Magento\Framework\App\RequestInterface;

class ContactPostPlugin
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param RequestInterface $request
     */
    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Prepend subject to comment before sending email
     *
     * @param Post $subject
     * @return void
     */
    public function beforeExecute(Post $subject)
    {
        $post = $this->request->getPostValue();
        if (!empty($post['subject']) && !empty($post['comment'])) {
            $post['comment'] = __('Subject: %1', $post['subject']) . "\n\n" . $post['comment'];
            $this->request->setPostValue($post);
        }
    }
}
