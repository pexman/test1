<?php
class Sitemap
{
    public static function generate(array $entries): string
    {
        $xml = new SimpleXMLElement('<urlset/>');
        $xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($entries as $entry) {
            $url = $xml->addChild('url');
            $url->addChild('loc', htmlspecialchars($entry['loc']));
            if (!empty($entry['lastmod'])) {
                $url->addChild('lastmod', $entry['lastmod']);
            }
            if (!empty($entry['changefreq'])) {
                $url->addChild('changefreq', $entry['changefreq']);
            }
            if (!empty($entry['priority'])) {
                $url->addChild('priority', $entry['priority']);
            }
        }

        return $xml->asXML();
    }
}
