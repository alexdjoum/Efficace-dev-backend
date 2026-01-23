<?php

namespace App\Services;

use Stichoza\GoogleTranslate\GoogleTranslate;
use Illuminate\Support\Facades\Cache;

class TranslationService
{
    protected $translator;
    protected $sourceLocale;
    protected $targetLocale;

    public function __construct()
    {
        $this->sourceLocale = 'fr'; 
        $this->targetLocale = app()->getLocale();
        $this->translator = new GoogleTranslate($this->targetLocale);
    }

    public function translate($text, $sourceLocale = null, $targetLocale = null)
    {
        if (empty($text)) {
            return $text;
        }

        $source = $sourceLocale ?? $this->sourceLocale;
        $target = $targetLocale ?? $this->targetLocale;

        if ($source === $target) {
            return $text;
        }

        $cacheKey = "translation.{$source}.{$target}." . md5($text);

        return Cache::remember($cacheKey, 86400, function () use ($text, $source, $target) {
            try {
                $this->translator->setSource($source);
                $this->translator->setTarget($target);
                return $this->translator->translate($text);
            } catch (\Exception $e) {
                \Log::error('Translation error: ' . $e->getMessage());
                return $text; 
            }
        });
    }

    public function translateBatch(array $texts, $sourceLocale = null, $targetLocale = null)
    {
        return array_map(function ($text) use ($sourceLocale, $targetLocale) {
            return $this->translate($text, $sourceLocale, $targetLocale);
        }, $texts);
    }
}