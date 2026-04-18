<?php

namespace App\Enums;

class LanguageCode
{
    const AFRIKAANS = 'af';
    const ALBANIAN = 'sq';
    const ARABIC = 'ar';
    const AZERBAIJANI = 'az';
    const BENGALI = 'bn';
    const BULGARIAN = 'bg';
    const CATALAN = 'ca';
    const CHINESE_CHN = 'zh_CN';
    const CHINESE_HKG = 'zh_HK';
    const CHINESE_TAI = 'zh_TW';
    const CROATIAN = 'hr';
    const CZECH = 'cs';
    const DANISH = 'da';
    const DUTCH = 'nl';
    const ENGLISH = 'en';
    const ENGLISH_UK = 'en_GB';
    const ENGLISH_US = 'en_US';
    const ESTONIAN = 'et';
    const FILIPINO = 'fil';
    const FINNISH = 'fi';
    const FRENCH = 'fr';
    const GEORGIAN = 'ka';
    const GERMAN = 'de';
    const GREEK = 'el';
    const GUJARATI = 'gu';
    const HAUSA = 'ha';
    const HEBREW = 'he';
    const HINDI = 'hi';
    const HUNGARIAN = 'hu';
    const INDONESIAN = 'id';
    const IRISH = 'ga';
    const ITALIAN = 'it';
    const JAPANESE = 'ja';
    const KANNADA = 'kn';
    const KAZAKH = 'kk';
    const KINYARWANDA = 'rw_RW';
    const KOREAN = 'ko';
    const KYRGYZ = 'ky_KG';
    const LAO = 'lo';
    const LATVIAN = 'lv';
    const LITHUANIAN = 'lt';
    const MACEDONIAN = 'mk';
    const MALAY = 'ms';
    const MALAYALAM = 'ml';
    const MARATHI = 'mr';
    const NORWEGIAN = 'nb';
    const PERSIAN = 'fa';
    const POLISH = 'pl';
    const PORTUGUESE_BR = 'pt_BR';
    const PORTUGUESE_POR = 'pt_PT';
    const PUNJABI = 'pa';
    const ROMANIAN = 'ro';
    const RUSSIAN = 'ru';
    const SERBIAN = 'sr';
    const SLOVAK = 'sk';
    const SLOVENIAN = 'sl';
    const SPANISH = 'es';
    const SPANISH_ARG = 'es_AR';
    const SPANISH_SPA = 'es_ES';
    const SPANISH_MEX = 'es_MX';
    const SWAHILI = 'sw';
    const SWEDISH = 'sv';
    const TAMIL = 'ta';
    const TELUGU = 'te';
    const THAI = 'th';
    const TURKISH = 'tr';
    const UKRAINIAN = 'uk';
    const URDU = 'ur';
    const UZBEK = 'uz';
    const VIETNAMESE = 'vi';
    const ZULU = 'zu';

    public static function values(): array
    {
        return [
            self::AFRIKAANS,
            self::ALBANIAN,
            self::ARABIC,
            self::AZERBAIJANI,
            self::BENGALI,
            self::BULGARIAN,
            self::CATALAN,
            self::CHINESE_CHN,
            self::CHINESE_HKG,
            self::CHINESE_TAI,
            self::CROATIAN,
            self::CZECH,
            self::DANISH,
            self::DUTCH,
            self::ENGLISH,
            self::ENGLISH_UK,
            self::ENGLISH_US,
            self::ESTONIAN,
            self::FILIPINO,
            self::FINNISH,
            self::FRENCH,
            self::GEORGIAN,
            self::GERMAN,
            self::GREEK,
            self::GUJARATI,
            self::HAUSA,
            self::HEBREW,
            self::HINDI,
            self::HUNGARIAN,
            self::INDONESIAN,
            self::IRISH,
            self::ITALIAN,
            self::JAPANESE,
            self::KANNADA,
            self::KAZAKH,
            self::KINYARWANDA,
            self::KOREAN,
            self::KYRGYZ,
            self::LAO,
            self::LATVIAN,
            self::LITHUANIAN,
            self::MACEDONIAN,
            self::MALAY,
            self::MALAYALAM,
            self::MARATHI,
            self::NORWEGIAN,
            self::PERSIAN,
            self::POLISH,
            self::PORTUGUESE_BR,
            self::PORTUGUESE_POR,
            self::PUNJABI,
            self::ROMANIAN,
            self::RUSSIAN,
            self::SERBIAN,
            self::SLOVAK,
            self::SLOVENIAN,
            self::SPANISH,
            self::SPANISH_ARG,
            self::SPANISH_SPA,
            self::SPANISH_MEX,
            self::SWAHILI,
            self::SWEDISH,
            self::TAMIL,
            self::TELUGU,
            self::THAI,
            self::TURKISH,
            self::UKRAINIAN,
            self::URDU,
            self::UZBEK,
            self::VIETNAMESE,
            self::ZULU,
        ];
    }

    public static function isValidLanguage($name): bool
    {
        $languages = self::values();
        foreach ($languages as $code => $langName) {
            if (strcasecmp($langName, $name) === 0) {
                return true;
            }
        }
        return false;
    }
}
