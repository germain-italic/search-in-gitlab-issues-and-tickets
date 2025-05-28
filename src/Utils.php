<?php
namespace App;

class Utils
{
    /**
     * Escape HTML entities
     * 
     * @param string $text
     * @return string
     */
    public static function escapeHtml($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Highlight search term in text
     * 
     * @param string $text
     * @param string $searchTerm
     * @return string
     */
    public static function highlightSearchTerm($text, $searchTerm)
    {
        if (empty($text) || empty($searchTerm)) {
            return $text;
        }
        
        // Escape HTML before highlighting
        $text = self::escapeHtml($text);
        $searchTerm = self::escapeHtml($searchTerm);
        
        // Escape regex special characters
        $searchTermRegex = preg_quote($searchTerm, '/');
        
        // Replace with highlighted version
        return preg_replace(
            "/({$searchTermRegex})/i",
            '<span class="highlight">$1</span>',
            $text
        );
    }
    
    /**
     * Format relative time
     * 
     * @param string $timestamp
     * @return string
     */
    public static function formatRelativeTime($timestamp)
    {
        $time = strtotime($timestamp);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $time);
        }
    }
}