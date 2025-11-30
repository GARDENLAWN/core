<?php

namespace GardenLawn\Core\Plugin\Mageplaza;

use Mageplaza\SocialLogin\Helper\Social;

class SocialHelperPlugin
{
    public function afterGetAuthUrl(Social $subject, $result): string
    {
        $parts = parse_url($result);
        if (isset($parts['path'])) {
            $parts['path'] = rtrim($parts['path'], '/');
        }
        if (isset($parts['query'])) {
            $parts['query'] = str_replace(' ', '&', $parts['query']);
        }
        return $parts['scheme'] . '://' . $parts['host'] . $parts['path'] . '?' . $parts['query'];
    }
}
