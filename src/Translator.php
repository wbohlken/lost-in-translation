<?php

namespace LostInTranslation;

use Illuminate\Translation\Translator as BaseTranslator;
use LostInTranslation\Events\MissingTranslationFound;
use LostInTranslation\Exceptions\MissingTranslationException;

class Translator extends BaseTranslator {

    /**
     * The current logger instance.
     *
     * @var \Illuminate\Log\Writer
     */
    protected $logger;

    /**
     * Get the translation for the given key.
     *
     * This method acts as a pass-through to Illuminate\Translation\Translator::get(), but verifies
     * that a replacement has actually been made.
     *
     * @throws MissingTranslationException When no replacement is made.
     *
     * @param  string       $key
     * @param  array        $replace
     * @param  string|null  $locale
     * @param  bool         $fallback
     *
     * @return string|array|null
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $translation = parent::get($key, $replace, $locale, $fallback);

        $isDefaultLocale = ($locale === config('lostintranslation.default_locale'));
        // The "translation" is unchanged from the key and the default locale is not used.
        if ($translation === $key && !$isDefaultLocale) {

            // Log the missing translation.
            if (config('lostintranslation.log')) {
                $this->logMissingTranslation($key, $replace, $locale, $fallback);
            }

            // Throw a MissingTranslationException if no translation was made.
            if (config('lostintranslation.throw_exceptions')) {
                throw new MissingTranslationException(
                    sprintf('Could not find translation for "%s".', $key)
                );
            }

            // Dispatch a MissingTranslationFound event.
            event(new MissingTranslationFound($key, $replace, $locale, $fallback));
        }

        return $translation;
    }

    /**
     * Log a missing translation.
     *
     * @param  string       $key
     * @param  array        $replace
     * @param  string|null  $locale
     * @param  bool         $fallback
     */
    protected function logMissingTranslation($key, $replace, $locale, $fallback)
    {
        if (! $this->logger) {
            $this->logger = logger();
            $this->logger->useFiles(storage_path('logs/lost-in-translation.log'));
        }

        $this->logger->notice('Missing translation: ' . $key, [
            'replacements' => $replace,
            'locale' => $locale ? $locale : config('app.locale'),
            'fallback' => $fallback ? config('app.fallback_locale') : '',
        ]);
    }
}
