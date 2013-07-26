<?php

/**
 * Accents Utility
 *
 * Defines functions to manage Unicode text de-accentuation
 * Settings may have to be modified or extended depending on supported languages
 */
class Accents extends Inflector {

	/**
	 * @var string: locale to use if none specified and current one unknown, or if specified one unknown
	 * French is set as default since it's the most common foreign language used in English (after latin)
	 */
	protected static $defaultLocale = 'fr';

	/**
	 * @var array: settings for each supported locale (de-accentuation may depend on language/collation)
	 * There MUST be settings for default locale
	 */
	protected static $settings = array(
		'fr' => array(
			// List of Unicode characters corresponding to two basic letters instead of one
			'doubleAccentList' => array('æ', 'Æ', 'ǽ', 'Ǽ', 'œ', 'Œ', 'ß', 'ĳ', 'Ĳ'),
			// Associates pairs of lowercase letters to their corresponding Unicode character
			'doubleLetters' => array(
				'ae' => array('æ', 'ǽ'),
				'oe' => array('œ'),
				'ss' => array('ß'),
				'ij' => array('ĳ'),
			),
			// Associates Unicode-character-matching patterns to their corresponding pair of letters
			'doubleAccents' => array(
				'/æ|ǽ/' => 'ae',
				'/Æ|Ǽ/' => 'AE',
				'/œ/' => 'oe',
				'/Œ/' => 'OE',
				'/ß/' => 'ss',
				'/ĳ/' => 'ij',
				'/Ĳ/' => 'IJ',
			),
			// Patterns to add or override in transliteration map
			'overriden' => array(
				'/æ|ǽ/' => 'ae',
				'/œ/' => 'oe',
				'/ü/' => 'u',
				'/Ä/' => 'A',
				'/Ü/' => 'U',
				'/Ö/' => 'O',
			),
			// Patterns to remove from transliteration map (after overrides)
			'deprecated' => array(
				'/ä|æ|ǽ/',
				'/ö|œ/',
			),
		),
	);

	/**
	 * @param string $locale
	 * @return array: settings for specified/current/default locale (highest priority to lowest)
	 * Default locale will be used instead of current one if an unknown locale is specified,
	 * so specify anything but a known locale (i.e. 'default') to prevent current locale from being used
	 */
	private static function getSettings($locale = null) {
		// Try to get current locale if none is specified (Config key may need to be corrected for application)
		$locale = empty($locale) ? Configure::read('Config.langCode') : $locale;
		// Select default locale if unrecognized or neither specified nor configured
		$locale = empty(self::$settings[$locale]) ? self::$defaultLocale : $locale;

		return self::$settings[$locale];
	}

	/**
	 * Corrects some de-accentuations in Inflector's transliteration map (i.e. 'ü' => 'ue')
	 * @param array $settings: settings to use according to locale
	 * @return array: correct map for de-accentuation
	 */
	private static function correctMap($settings) {
		$map = self::$_transliteration;

		// Override incorrect transliterations
		foreach ($settings['overriden'] as $pattern => $letter) {
			$map[$pattern] = $letter;
		}
		// Remove remaining incorrect transliterations
		foreach ($settings['deprecated'] as $pattern) {
			unset($map[$pattern]);
		}

		return $map;
	}

	/**
	 * Replaces accentuated or special letters from given text with corresponding basic letter(s)
	 * @param string $text
	 * @param string $locale: settings for specified/current/default locale (highest priority to lowest) will be used
	 * @return string 
	 */
	public static function strip($text, $locale = null) {
		// Get transliteration map for locale
		$map = self::correctMap(self::getSettings($locale));
		return preg_replace(array_keys($map), array_values($map), $text);
	}

	/**
	 * Wrapper for Inflector::slug(): makes slugs lowercase and uses '-' as default separator
	 * @param string $string
	 * @param string $locale: settings for specified/current/default locale (highest priority to lowest) will be used
	 * @param string $separator: word separator to use in slug
	 * @return string: given string made URL-friendly 
	 */
	public static function slug($string, $locale = null, $separator = '-') {
		return mb_strtolower(parent::slug(self::strip($string, $locale), $separator));
	}

	/**
	 * @param string $pattern
	 * @param string $locale: settings for specified/current/default locale (highest priority to lowest) will be used
	 * @return array: all patterns equivalent to given one, considering Unicode characters corresponding to two letters
	 * (i.e. 'caesar' expands to 'caesar', 'cæsar', 'cǽsar')
	 */
	public static function expandDoubleLetters($pattern, $locale = null) {
		$settings = self::getSettings($locale);
		// Construct array associating each pattern to a search index (start at 0)
		$patterns = array(strtolower(self::strip($pattern, $locale)) => 0);

		do {
			foreach ($patterns as $pattern => $lastPosition) {
				$firstIndex = false;
				// Find first position of potentially Unicode pairs of letters
				foreach ($settings['doubleLetters'] as $letters => $accents) {
					if (($index = mb_strpos($pattern, $letters, $lastPosition)) !== false) { // Pair found
						// Keep minimal position
						if ($firstIndex === false || $index < $firstIndex) {
							$firstIndex = $index;
							$firstAccents = $accents;
						}
					}
				}
				if ($firstIndex !== false) { // There was a match
					// Shift search index for this pattern
					$patterns[$pattern] = $firstIndex + 2;
					foreach ($firstAccents as $accent) {
						// Create new pattern for each Unicode character corresponding to found pair of letters
						$newPattern = mb_substr($pattern, 0, $firstIndex) . $accent . mb_substr($pattern, $firstIndex + 2);
						$patterns[$newPattern] = $firstIndex + 1;
					}
					// Restart search on the whole pattern array
					break;
				}
			}
		} while ($firstIndex !== false);

		return array_keys($patterns);
	}

	/**
	 * @param string $text
	 * @param string $locale: settings for specified/current/default locale (highest priority to lowest) will be used
	 * @return array associating their position to double-letter Unicode characters found in given text
	 */
	public static function findDoubleAccents($text, $locale = null) {
		$settings = self::getSettings($locale);
		$accents = array();
		
		foreach ($settings['doubleAccentList'] as $accent) {
			// Find all occurences of this double-letter Unicode character in text
			for ($index = 0; $index < mb_strlen($text) && ($position = mb_strpos($text, $accent, $index)) !== false; $index = $position + 1) {
				$accents[$position] = $accent;
			}
		}
		return $accents;
	}

}

?>
