<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Converts a docs/user-guides/*.md file to HTML, with a plain id="slug" on
 * every heading (no visible icon — symbol is blank, insert stays 'after'
 * since 'none' would suppress the id too) so anchor links like
 * "#3-recurring-invoices" actually scroll to that section. Shared by
 * HelpController (staff) and the portal FAQ controllers (client/partner).
 */
class MarkdownGuideRenderer
{
    public function render(string $path): string
    {
        $environment = new Environment(['heading_permalink' => ['id_prefix' => '', 'symbol' => '']]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        return (new MarkdownConverter($environment))->convert(file_get_contents($path))->getContent();
    }
}
