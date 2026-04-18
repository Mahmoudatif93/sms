<?php

namespace App\Domain\Chatbot\Services;

use App\Domain\Chatbot\DTOs\KnowledgeSearchResultDTO;
use App\Domain\Chatbot\Repositories\ChatbotRepositoryInterface;
use App\Models\ChatbotKnowledgeBase;
use Illuminate\Support\Collection;

class KnowledgeSearchService
{
    private array $arabicStopWords = [
        'من', 'في', 'على', 'إلى', 'عن', 'مع', 'هذا', 'هذه', 'ذلك', 'تلك',
        'الذي', 'التي', 'هو', 'هي', 'أنا', 'نحن', 'أنت', 'أنتم', 'هم', 'هن',
        'كان', 'كانت', 'يكون', 'تكون', 'ما', 'لا', 'لم', 'لن', 'قد', 'وقد',
        'أو', 'ثم', 'حتى', 'إذا', 'إذ', 'كل', 'بعض', 'غير', 'بين', 'حول',
        'و', 'ف', 'ب', 'ل', 'ك', 'أ', 'إ',
    ];

    private array $englishStopWords = [
        'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
        'should', 'may', 'might', 'must', 'shall', 'can', 'need', 'dare',
        'ought', 'used', 'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by',
        'from', 'as', 'into', 'through', 'during', 'before', 'after', 'above',
        'below', 'between', 'under', 'again', 'further', 'then', 'once',
        'i', 'you', 'he', 'she', 'it', 'we', 'they', 'what', 'which', 'who',
        'this', 'that', 'these', 'those', 'am', 'and', 'but', 'if', 'or',
    ];

    public function __construct(
        private ChatbotRepositoryInterface $repository
    ) {}

    public function search(string $channelId, string $query, string $language = 'ar'): KnowledgeSearchResultDTO
    {
        // 1. Normalize the query
        $normalizedQuery = $this->normalizeQuery($query, $language);

        if (empty($normalizedQuery)) {
            return KnowledgeSearchResultDTO::empty();
        }

        // 2. Try keyword matching first (fast)
        $keywordMatch = $this->searchByKeywords($channelId, $query);
        if ($keywordMatch) {
            return KnowledgeSearchResultDTO::fromResults(
                collect([$keywordMatch]),
                0.95
            );
        }

        // 3. Full-text search
        $results = $this->repository->searchKnowledge($channelId, $normalizedQuery, $language);

        if ($results->isEmpty()) {
            return KnowledgeSearchResultDTO::empty();
        }

        // 4. Calculate confidence based on match quality
        $confidence = $this->calculateConfidence($query, $results->first(), $language);

        return KnowledgeSearchResultDTO::fromResults($results, $confidence);
    }

    public function normalizeQuery(string $query, string $language = 'ar'): string
    {
        // Remove special characters
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query);

        // Convert to lowercase
        $normalized = mb_strtolower($normalized);

        // Split into words
        $words = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        // Remove stop words
        $stopWords = $language === 'ar' ? $this->arabicStopWords : $this->englishStopWords;
        $words = array_filter($words, fn($word) => !in_array($word, $stopWords) && mb_strlen($word) > 1);

        return implode(' ', $words);
    }

    private function searchByKeywords(string $channelId, string $message): ?ChatbotKnowledgeBase
    {
        $knowledge = $this->repository->getKnowledge($channelId, true);

        foreach ($knowledge as $item) {
            if ($item->matchesKeyword($message)) {
                return $item;
            }
        }

        return null;
    }

    private function calculateConfidence(string $query, ChatbotKnowledgeBase $match, string $language): float
    {
        $queryWords = $this->getWords($query);
        $questionWords = $this->getWords($match->getQuestion($language) ?? '');
        $keywordWords = $match->keywords ?? [];

        if (empty($queryWords)) {
            return 0.0;
        }

        // Check keyword overlap
        $keywordMatches = 0;
        foreach ($queryWords as $word) {
            foreach ($keywordWords as $keyword) {
                if (str_contains(mb_strtolower($keyword), mb_strtolower($word)) ||
                    str_contains(mb_strtolower($word), mb_strtolower($keyword))) {
                    $keywordMatches++;
                    break;
                }
            }
        }

        if ($keywordMatches > 0) {
            return min(0.7 + ($keywordMatches * 0.1), 0.95);
        }

        // Check question word overlap
        $questionMatches = 0;
        foreach ($queryWords as $word) {
            foreach ($questionWords as $qWord) {
                if (similar_text(mb_strtolower($word), mb_strtolower($qWord)) > 3) {
                    $questionMatches++;
                    break;
                }
            }
        }

        $overlapRatio = $questionMatches / count($queryWords);
        return min($overlapRatio * 0.9, 0.85);
    }

    private function getWords(string $text): array
    {
        return preg_split('/\s+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    }

    public function detectLanguage(string $text): string
    {
        // Simple detection based on character ranges
        $arabicPattern = '/[\x{0600}-\x{06FF}]/u';

        if (preg_match($arabicPattern, $text)) {
            return 'ar';
        }

        return 'en';
    }
}
