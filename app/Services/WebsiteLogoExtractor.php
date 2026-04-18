<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;


class WebsiteLogoExtractor
{
    /**
     * Extract logo from a website
     *
     * @param string $url Website URL
     * @return array Result of logo extraction with success status and logo URL if successful
     */
    public function extractLogo(string $url): array
    {
        try {
            // Normalize URL
            $normalizedUrl = $this->normalizeUrl($url);
            
            // Fetch the HTML content
            $html = $this->fetchWebsiteContent($normalizedUrl);
            if (!$html) {
                return ['success' => false, 'message' => 'Failed to fetch website content'];
            }
            
            // Look for logo in common locations
            $logoUrl = $this->findLogo($html, $normalizedUrl);
            
            if ($logoUrl) {
                // Save logo to local storage
                $savedPath = $this->saveLogoToStorage($logoUrl);
                return [
                    'success' => true, 
                    'logo_url' => $savedPath,
                    'original_logo_url' => $logoUrl
                ];
            }
            
            return ['success' => false, 'message' => 'No logo found on the website'];
        } catch (Exception $e) {
            Log::error('Logo extraction failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Logo extraction failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Normalize the provided URL
     *
     * @param string $url
     * @return string
     */
    private function normalizeUrl(string $url): string
    {
        // Add protocol if missing
        if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
            $url = 'https://' . $url;
        }
        
        // Remove trailing slash
        $url = rtrim($url, '/');
        
        return $url;
    }
    
    /**
     * Fetch website content using Guzzle HTTP client
     *
     * @param string $url
     * @return string|null
     */
    private function fetchWebsiteContent(string $url): ?string
    {
        try {
            $client = new Client([
                'timeout' => 10,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                ]
            ]);
            
            $response = $client->get($url);
            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            Log::error('Failed to fetch website: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Find logo in HTML content using various strategies
     *
     * @param string $html
     * @param string $baseUrl
     * @return string|null
     */
    private function findLogo(string $html, string $baseUrl): ?string
    {
        $crawler = new \Crawler($html);
        
        // Strategy 1: Look for elements with "logo" in ID, class, or alt text
        $logoSelectors = [
            'img[id*=logo]', 
            'img[class*=logo]', 
            'img[alt*=logo]',
            'svg[id*=logo]',
            'svg[class*=logo]',
            'a[class*=logo] img',
            'div[class*=logo] img',
            'h1[class*=logo] img',
        ];
        
        foreach ($logoSelectors as $selector) {
            $elements = $crawler->filter($selector);
            if ($elements->count() > 0) {
                $element = $elements->first();
                if ($element->nodeName() === 'img') {
                    $src = $element->attr('src');
                    if ($src) {
                        return $this->makeAbsoluteUrl($src, $baseUrl);
                    }
                } elseif ($element->nodeName() === 'svg') {
                    // For SVG, we'd need to save the SVG content
                    // This is a simplification - in a real implementation,
                    // you'd need to handle SVG differently
                    $svgContent = $element->outerHtml();
                    return $this->saveSvgContent($svgContent, $baseUrl);
                }
            }
        }
        
        // Strategy 2: Look for Apple touch icon or favicon
        $iconSelectors = [
            'link[rel="apple-touch-icon"]',
            'link[rel="apple-touch-icon-precomposed"]',
            'link[rel="icon"]',
            'link[rel="shortcut icon"]'
        ];
        
        foreach ($iconSelectors as $selector) {
            $elements = $crawler->filter($selector);
            if ($elements->count() > 0) {
                $href = $elements->first()->attr('href');
                if ($href) {
                    return $this->makeAbsoluteUrl($href, $baseUrl);
                }
            }
        }
        
        // Strategy 3: Check if there's a logo at standard locations
        $standardLocations = [
            '/logo.png',
            '/images/logo.png',
            '/assets/logo.png',
            '/img/logo.png',
            '/static/logo.png',
            '/assets/images/logo.png',
        ];
        
        foreach ($standardLocations as $location) {
            $url = $baseUrl . $location;
            if ($this->urlExists($url)) {
                return $url;
            }
        }
        
        // Strategy 4: Use default favicon.ico
        if ($this->urlExists($baseUrl . '/favicon.ico')) {
            return $baseUrl . '/favicon.ico';
        }
        
        // Nothing found
        return null;
    }
    
    /**
     * Make relative URL absolute by combining with base URL
     *
     * @param string $url
     * @param string $baseUrl
     * @return string
     */
    private function makeAbsoluteUrl(string $url, string $baseUrl): string
    {
        // If URL is already absolute
        if (preg_match('~^(?:f|ht)tps?://~i', $url)) {
            return $url;
        }
        
        // If URL is protocol-relative
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        
        // If URL is absolute path
        if (str_starts_with($url, '/')) {
            // Get domain from base URL
            $parts = parse_url($baseUrl);
            $domain = $parts['scheme'] . '://' . $parts['host'];
            return $domain . $url;
        }
        
        // If URL is relative path
        return $baseUrl . '/' . $url;
    }
    
    /**
     * Check if a URL exists by sending a HEAD request
     *
     * @param string $url
     * @return bool
     */
    private function urlExists(string $url): bool
    {
        try {
            $client = new Client([
                'timeout' => 5,
                'verify' => false,
            ]);
            
            $response = $client->head($url);
            return $response->getStatusCode() == 200;
        } catch (GuzzleException $e) {
            return false;
        }
    }
    
    /**
     * Save logo to local storage
     *
     * @param string $url
     * @return string The path to the saved logo
     */
    private function saveLogoToStorage(string $url): string
    {
        try {
            // Generate unique filename
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
            $extension = $extension ?: 'png'; // Default to png if no extension
            $filename = 'logos/' . md5($url) . '.' . $extension;
            
            // Download and save file
            $client = new Client(['verify' => false]);
            $response = $client->get($url);
            
            Storage::put($filename, $response->getBody()->getContents());
            
            return Storage::url($filename);
        } catch (Exception $e) {
            Log::error('Failed to save logo: ' . $e->getMessage());
            // Return original URL if we can't save it
            return $url;
        }
    }
    
    /**
     * Save SVG content to a file
     *
     * @param string $svgContent
     * @param string $baseUrl
     * @return string
     */
    private function saveSvgContent(string $svgContent, string $baseUrl): string
    {
        try {
            $filename = 'logos/' . md5($baseUrl) . '.svg';
            
            Storage::put($filename, $svgContent);
            
            return Storage::url($filename);
        } catch (Exception $e) {
            Log::error('Failed to save SVG: ' . $e->getMessage());
            return null;
        }
    }
}