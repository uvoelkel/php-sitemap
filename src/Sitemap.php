<?php

namespace Voelkel\Sitemap;

class Sitemap
{
    const MAX_ENTRIES = 50000;

    const MAX_SIZE_BYTES = 10485760; // 10MB uncompressed

    const EMTPY_SITEMAP_SIZE_BYTES = 110; // size of the file without any url entries (but non shortened urlsetnode)

    const EMPTY_ENTRY_SIZE_BYTES = 141;

    /** @var string */
    private $directory;

    /** @var string */
    private $domain;

    /** @var \DOMDocument */
    private $dom = null;

    /** @var \DOMNode */
    private $root = null;

    /** @var int */
    private $entryCount = 0;

    /** @var int */
    private $sizeInBytes = 0;

    /** @var int */
    private $sitemapCount = 0;

    /** @var array */
    private $sitemapsWritten = [];

    /**
     * @param string $directory
     * @param string $domain
     */
    public function __construct($directory, $domain)
    {
        $this->directory = $directory;
        $this->domain = $domain;
    }

    /**
     * @return int
     */
    public function getSitemapCount()
    {
        return $this->sitemapCount;
    }

    public function getSitemaps()
    {
        return $this->sitemapsWritten;
    }

    /**
     * @param string $url
     * @param string|null $changefreq
     * @param float|null $priority
     * @throws \Exception
     */
    public function append($url, $changefreq = 'weekly', $priority = 0.5)
    {
        if (null === $this->dom) {
            $this->setupDocument();
        }

        $urlNode = $this->root->appendChild($this->dom->createElement('url'));

        $locNode = $urlNode->appendChild($this->dom->createElement('loc'));
        $locNode->appendChild($this->dom->createTextNode($url));

        $lastmodNode = $urlNode->appendChild($this->dom->createElement('lastmod'));
        $lastmodNode->appendChild($this->dom->createTextNode(date(DATE_W3C)));

        $changefreqNode = $urlNode->appendChild($this->dom->createElement('changefreq'));
        $changefreqNode->appendChild($this->dom->createTextNode($changefreq));

        $priorityNode = $urlNode->appendChild($this->dom->createElement('priority'));
        $priorityNode->appendChild($this->dom->createTextNode((string)$priority));

        $this->entryCount++;
        $this->sizeInBytes += self::EMPTY_ENTRY_SIZE_BYTES + strlen($url) + strlen($changefreq);

        if ($this->isFull()) {
            $this->save();
        }
    }

    /**
     * @param string $filename
     * @return int
     * @throws \Exception
     */
    public function save($filename = 'robot-sitemap-%d.xml')
    {
        if (0 === $this->entryCount) {
            return 0;
        }

        $filename = sprintf($filename, $this->sitemapCount);
        $pathname = $this->directory . '/' . $filename;

        $written = $this->dom->save($pathname);
        if ($written !== $this->sizeInBytes) {
            throw new \Exception(sprintf('%d bytes written. expected %d', $written, $this->sizeInBytes));
        }

        if (function_exists('gzopen')) {
            $gz = gzopen($pathname . '.gz','w9');
            gzwrite($gz, file_get_contents($pathname));
            gzclose($gz);

            $filename .= '.gz';
            unlink($pathname);
        }

        $this->sitemapsWritten[] = [
            'filename' => $filename,
            'datetime' => new \DateTime(),
        ];

        $this->dom = null;

        return $written;
    }

    public function writeIndex()
    {
        if (null !== $this->dom) {
            throw new \Exception('current sitemap not saved.');
        }

        $this->setupDocument('sitemapindex');

        foreach ($this->sitemapsWritten as $sitemap) {
            $sitemapNode = $this->root->appendChild($this->dom->createElement('sitemap'));

            $locNode = $sitemapNode->appendChild($this->dom->createElement('loc'));
            $locNode->appendChild($this->dom->createTextNode($this->domain . '/' . $sitemap['filename']));

            $lastmodNode = $sitemapNode->appendChild($this->dom->createElement('lastmod'));
            $lastmodNode->appendChild($this->dom->createTextNode($sitemap['datetime']->format(DATE_W3C)));
        }

        $this->dom->save($this->directory . '/robot-sitemap-index.xml');

        $this->dom = null;
        $this->sitemapCount = 0;
        $this->sitemapsWritten = [];
    }

    private function setupDocument($root = 'urlset')
    {
        $this->dom = new \DOMDocument('1.0','UTF-8');
        $this->dom->formatOutput = true;

        $this->root = $this->dom->appendChild($this->dom->createElement($root));

        $attr = $this->dom->createAttribute('xmlns');
        $attr->value= 'http://www.sitemaps.org/schemas/sitemap/0.9';
        $this->root->appendChild($attr);

        $this->entryCount = 0;
        $this->sizeInBytes = self::EMTPY_SITEMAP_SIZE_BYTES;
        $this->sitemapCount++;
    }

    private function isFull()
    {
        if ($this->entryCount >= self::MAX_ENTRIES) {
            return true;
        }

        if ($this->sizeInBytes + (2 * self::EMPTY_ENTRY_SIZE_BYTES) > self::MAX_SIZE_BYTES) {
            return true;
        }

        return false;
    }
}

