<?php

App::import('Search.Lib', 'Accents');

/**
 * Searchable Behavior
 * 
 * Models behaving as Searchable may use custom find('search') with some custom find options:
 * 
 * 'searchQuery' specifies a string containing words and quoted strings to search for as patterns.
 * If empty, no filtering or result highlighting is performed.
 * 
 * The following 'searchFields' and 'displayFields' keys may be used independently or together.
 * 
 * 
 * The 'searchFields' key is used for database filtering.
 * It indicates what fields to search for patterns in database (those may be fields constructed in Model beforeFind()).
 * Model records with no specified field matching any given pattern will be filtered out if this key is present.
 * If $query['searchFields'] is set to true, the 'searchFields' default array specified in Model settings is used instead.
 * 
 * 
 * The 'displayFields' key is used for highlighting text matches in find results.
 * 'displayFields' indicates what fields to search for patterns in find results (those may be fields constructed in Model afterFind()).
 * For each matching field '<field>' in find results, a virtual field named '<field>_snippet' is added at same level with matches highlighted.
 * If $query['displayFields'] is set to true, the 'displayFields' default array specified in Model settings is used instead.
 * 
 * This array must mimic the structure of a record in your Model's find results. For example, if you search for model Page
 * with Page hasMany Block and Block hasOne Text, and your find results follow Cake's conventions, they should look like:
 * 	array(
 * 		0 => array(
 * 			'Page' => array(
 * 				'<some_field>' => <some_value>,
 * 				...
 * 			),
 * 			'Block' => array(
 * 				0 => array(
 * 					'<some_field>' => <some_value>,
 * 					...
 * 					'Text' => array(
 * 						'<some_field>' => <some_value>,
 * 						...
 * 					),
 * 				),
 * 				1 => array(
 * 					'<some_field>' => <some_value>,
 * 					...
 * 					'Text' => array(
 * 						'<some_field>' => <some_value>,
 * 						...
 * 					),
 * 				),
 * 			),
 * 		),
 * 		1 => array(
 * 			'Page' => array(
 * 				'<some_field>' => <some_value>,
 * 				...
 * 			),
 * 			'Block' => array(),
 * 		),
 * 		...
 * 	)
 * 
 * The find('search') operation will search each element of this result array following the structure specified in the 'displayFields' array,
 * so you must specify a key for every Model or primarily associated Model to search (in this case, 'Page' and 'Block').
 * This key must be associated to a sub-array with a structure similar to the inside of the searched Model sub-array in results.
 * 
 * - If you want to match a field of this Model (like in Page), specify it as a key of your sub-array, associated to a boolean.
 * This boolean indicates if the searched field is long text (has been sanitized, and only snippets must be displayed).
 * 
 * - If you must iterate over several records of this Model (like in Block), you cannot specify all possible numeric keys,
 * so just specify one in your sub-array and associate it to an array with a structure similar to the inside of the searched Model,
 * and all numeric keys at that level will be treated.
 * 
 * - If you want to search in a deeper associated Model, specify its name as a key of your sub-array, associated to an array
 * with a structure similar to the inside of the deeper associated Model.
 * 
 * So for the find results structure shown above, if you want to highlight matches in Page title and description, Block title
 * and Text contents, your 'displayFields' array should be:
 * 	array(
 * 		'Page' => array(
 * 			'title' => false,
 * 			'description' => false,
 * 		),
 * 		'Block' => array(
 * 			0 => array(
 * 				'title' => false,
 * 				'Text' => array(
 * 					'contents' => true,
 * 				),
 * 			),
 * 		),
 * 	)
 * All Blocks will be searched even with only the 0 key defined inside the 'Block' array.
 * The Text 'contents' key is set to true to indicate markup presence (see 'markupReplacements' key in settings) and
 * text possible great length, which will be reduced (see 'snippetSettings' key in settings).
 * 
 * You may specify keys for Models or fields which presence is uncertain: any difference of structure between the find results and
 * the 'displayFields' array will be ignored without generating any error.
 * 
 * 
 * A 'locale' key can be used to specify which locale de-accentuation will consider (see Accent::$settings).
 * This key is used for database matching or search results display, or both if they are both enabled.
 * If no locale is specified, current one will be used instead. If current locale can't be determined or if an unknown
 * locale is specified (i.e. 'default'), default locale (Accent::$defaultLocale) will be used.
 * 
 * 
 * Finally, a 'manageAccents' key can be used to enable or disable accents management (default is $this->manageAccents).
 * Since MySQL makes some accent management on its own, you may have search results with no highlighted matches if you
 * disable accents management (match in database but no match in PHP).
 */
class SearchableBehavior extends ModelBehavior {

	/**
	 * @var array: default settings for Models. Modify and override at will.
	 */
	private $defaults = array(
		// Default fields to search for text in database
		'searchFields' => array(
			'I18n__title.content' => false,
			'I18n__contents.content' => true,
		),
		// Default fields to search for text in find results
		'displayFields' => array(),
		// Default settings for search results display
		'snippetSettings' => array(
			'context' => array(
				'min' => 20, // Minimum length of text to display left and right of matches (when numerous matches)
				'max' => 80, // Maximum length of text to display left and right of matches (when single match)
				'minInterval' => 10, // Matches (plus context) separated by interval shorter than this number will be merged
			),
			'snippet' => array(
				'before' => '<span class="search-snippet">', // String to display before snippets
				'after' => '</span>', // String to display after snippets
				'between' => '<span class="search-snippet-separator"> [...] </span>', // String to display between snippets
			),
			'match' => array(
				'before' => '<span class="search-match">', // String to display before matches
				'after' => '</span>', // String to display after matches
			),
		),
		// Characters sanitized for database storage and their replacement string
		'markupReplacements' => array(
			'&' => '&AMP;',
			'<' => '&LT;',
			'>' => '&GT;',
		),
		// Database text matching will be tried using every one of these collations (for unusual languages)
		'managedCollations' => array(
			'utf8_general_ci',
		),
	);

	/**
	 * @var array: adds pattern for _findSearch() since methods beginning with underscore are normally not recognized by Models
	 */
	public $mapMethods = array('/\b_findSearch\b/' => '_findSearch');

	/**
	 * @var string: stores locale for current find('search')
	 */
	private $locale = null;

	/**
	 * @var boolean: default behavior about accent management: false if it is to be skipped
	 */
	private $manageAccents = true;

	/**
	 * @var object: database connection
	 */
	protected $mySQLi = null;

	/**
	 * @var array: database configuration 
	 */
	protected $dataBaseConfig = null;

	/**
	 * Behavior setup
	 * @param object $Model
	 * @param array $settings 
	 */
	public function setup(&$Model, $settings = array()) {
		// Activate 'search' find type
		$Model->findMethods['search'] = true;
		// Allow Model override of default settings
		$this->settings[$Model->alias] = array_merge($this->defaults, $settings);
	}

	/**
	 * Custom find type: called before and after a find('search')
	 * @param object $Model
	 * @param object $call
	 * @param string $state
	 * @param array $query: find options array
	 * @param array $results: find results (when called after find)
	 * @return type 
	 */
	public function _findSearch(&$Model, $call, $state, $query, $results = array()) {
		// Try to get current locale if none is specified (Config key may need to be corrected for application)
		$this->locale = empty($query['locale']) ? Configure::read('Config.langCode') : $query['locale'];
		// Allow query override of default behavior about accent management
		$this->manageAccents = array_key_exists('manageAccents', $query) ? !!$query['manageAccents'] : $this->manageAccents;

		if ($state == 'before') { // The find query ($options array) can be modified
			// Check if there is anything to search, or if operation is a find('count'). In this case, do nothing
			if (empty($query['searchQuery']) || empty($query['searchFields']) || (!empty($query['operation']) && $query['operation'] == 'count')) {
				return $query;
			}

			// Extract patterns from query
			$patterns = $this->getPatterns($query['searchQuery']);
			if (empty($patterns)) {
				return $query;
			}
			$patterns = $this->getSqlPatterns($patterns);

			// Get default field list if none is specified
			if (!is_array($query['searchFields'])) {
				$query['searchFields'] = $this->settings[$Model->alias]['searchFields'];
			}

			// Add conditions for each specified field
			$conditions = array();
			foreach ($query['searchFields'] as $field => $markup) {
				$conditions = array_merge($this->matchFieldPatterns($Model, $field, $patterns, $markup), $conditions);
			}

			if (empty($query['conditions'])) { // There were no find conditions
				$query['conditions'] = array('OR' => $conditions);
			} else if (empty($query['conditions']['OR'])) { // 'OR' key is free in conditions array
				$query['conditions']['OR'] = $conditions;
			} else {
				// Keep existing 'OR' conditions
				$query['conditions']['OR'] = array_merge($query['conditions']['OR'], $conditions);
			}

			return $query;
		} else { // The find results can be modified
			// Check if there is anything to search
			if (empty($query['searchQuery']) || empty($query['displayFields']) || (!empty($query['operation']) && $query['operation'] == 'count')) {
				return $results;
			}

			// Extract patterns from query
			$patterns = $this->getPatterns($query['searchQuery']);
			if (empty($patterns)) {
				return $results;
			}
			$patterns = $this->getPhpPatterns($patterns);

			// Get default field array if none is specified
			if (!is_array($query['displayFields'])) {
				$query['displayFields'] = $this->settings[$Model->alias]['displayFields'];
			}

			foreach ($results as $index => $result) {
				$results[$index] = $this->displaySnippets($Model, $result, $query['displayFields'], $patterns);
			}

			return $results;
		}
	}

	/**
	 * @param string $searchQuery 
	 * @return array: patterns (words or quoted strings) to match from given search query
	 */
	private function getPatterns($searchQuery) {
		// Extract quoted subqueries
		$queries = explode('"', $searchQuery);
		$patterns = array();

		for ($index = 0; $index < count($queries); $index++) {
			if ($index % 2 && trim($queries[$index]) != '') { // Subquery is not empty and was quoted
				// Store it as it is
				$patterns[] = $queries[$index];
			} else { // Unquoted subquery: divide into words
				$words = explode(' ', $queries[$index]);
				foreach ($words as $word) {
					// Remove surrounding blank space
					if (($word = trim($word)) != '') {
						$patterns[] = $word;
					}
				}
			}
		}

		return $patterns;
	}

	/**
	 * @param array $patterns
	 * @return array: given patterns modified for database matching
	 */
	private function getSqlPatterns($patterns) {
		$sqlPatterns = array();
		if ($this->manageAccents) {
			// Manage accents in addition to escaping
			foreach ($patterns as $pattern) {
				// Add all Unicode words equivalent to this pattern (i.e. 'caesar' expands to 'caesar', 'cæsar', 'cǽsar')
				$sqlPatterns = array_merge($sqlPatterns, Accents::expandDoubleLetters($this->escapeMySqlPattern($pattern), $this->locale));
			}
		} else {
			// Just escape patterns for MySQL syntax
			foreach ($patterns as $pattern) {
				$sqlPatterns[] = $this->escapeMySqlPattern($pattern);
			}
		}

		return $sqlPatterns;
	}

	/**
	 * @param array $patterns
	 * @return array: given patterns modified for PHP matching
	 */
	private function getPhpPatterns($patterns) {
		foreach ($patterns as $index => $pattern) {
			if ($this->manageAccents) {
				// De-accentuate pattern
				$pattern = Accents::strip($pattern, $this->locale);
			}
			// Make pattern upper case
			$patterns[$index] = mb_strtoupper($pattern);
		}

		return $patterns;
	}

	/**
	 * @param string $pattern
	 * @return string: given pattern escaped for MySQL matching
	 */
	private function escapeMySqlPattern($pattern) {
		// Check database connection
		if (empty($this->mySQLi)) {
			// Check database configuration
			if (empty($this->dataBaseConfig)) {
				$dataSource = ConnectionManager::getDataSource('cms');
				// Get database configuration
				$this->dataBaseConfig = $dataSource->config;
			}
			// Get database connection
			$this->mySQLi = new mysqli($this->dataBaseConfig['host'], $this->dataBaseConfig['login'], $this->dataBaseConfig['password'], $this->dataBaseConfig['database']);
		}
		// Escape pattern for matching with operator LIKE
		return preg_replace('/([.*?+\[\]{}^$|(\)])/', '\\\\\1', $this->mySQLi->real_escape_string($pattern));
	}

	/**
	 * $param object $Model
	 * @param string $field: database field to search
	 * @param array $patterns: patterns as strings
	 * @param boolean $markup: true if searched field contains markup (then patterns must be sanitized)
	 * @return array: conditions array to match given field against given patterns (to be put in an 'OR' find condition key)
	 */
	private function matchFieldPatterns($Model, $field, $patterns, $markup = false) {
		$markupReplacements = $this->settings[$Model->alias]['markupReplacements'];
		$conditions = array();
		foreach ($patterns as $pattern) {
			if ($markup) { // Some characters have been sanitized for database storage
				// Sanitize pattern as well
				$pattern = str_replace(array_keys($markupReplacements), array_values($markupReplacements), $pattern);
			}
			// Add conditions to match this field against this pattern for each managed collation
			foreach ($this->settings[$Model->alias]['managedCollations'] as $collation) {
				$conditions[] = sprintf("%s LIKE '%%%s%%' COLLATE %s", $field, $pattern, $collation);
			}
		}

		return $conditions;
	}

	/**
	 * Recursively walks through given find results according to the structure of given $fields array.
	 * For each key not associated with an array in $fields, searches contents of corresponding key in $results.
	 * If any given pattern is found, creates a virtual field at same level in $results, with matches highlighted.
	 * For each numeric key associated with an array, walks through all elements of $results (at current level).
	 * For each other key associated with an array, walks through the element at that key in $results.
	 * @param object $Model
	 * @param array $result
	 * @param array $fields
	 * @param array $patterns
	 * @return array 
	 */
	private function displaySnippets($Model, $result, $fields, $patterns) {

		foreach ($fields as $key => $field) {
			if (is_numeric($key)) {
				if (!is_array($result)) {
					return $result;
				}
				foreach ($result as $index => $record) {
					$result[$index] = $this->displaySnippets($Model, $record, $field, $patterns);
				}
			} else if (!array_key_exists($key, $result)) {
				continue;
			} else if (is_array($field)) {
				$result[$key] = $this->displaySnippets($Model, $result[$key], $field, $patterns);
			} else {
				$result[$key . '_snippet'] = $this->highlightMatches($Model, $result[$key], $patterns, $field);
			}
		}
		return $result;
	}

	/**
	 * Constructs result text for the search of given patterns in given text:
	 * Adds markup left and right of matches
	 * Keeps only snippets of text surrounding matches if text is long
	 * Adds markup left and right of these snippets, and between them
	 * $param object $Model
	 * @param string $text
	 * @param array $patterns 
	 * @param boolean $markup: true if text is long (may contain markup to ignore, and only text surrounding matches is to be displayed)
	 * @param array $settings: display settings (see $this->default['snippetSettings'] for default settings)
	 * @return string: text to display
	 */
	private function highlightMatches($Model, $text, $patterns, $markup = false, $settings = null) {
		if (empty($settings)) {
			$settings = $this->settings[$Model->alias]['snippetSettings'];
		}
		if ($markup) {
			// Convert text from HTML to text, reduce blank spaces to minimal
			$text = preg_replace('/\s+/', ' ', strip_tags($text));
		}

		// Get uppercase and unaccentuated (if accent management is not disabled) copy of text for case-insensitive search
		$textLength = mb_strlen($upText = mb_strtoupper($this->manageAccents ? Accents::strip($text, $this->locale) : $text));

		$matches = array();
		// Store all matches with all patterns
		foreach ($patterns as $pattern) {
			$length = mb_strlen($pattern);
			// Store all matches with this pattern
			for ($start = 0; ($index = mb_strpos($upText, $pattern, $start)) !== false; $start = $index + 1) {
				if (isset($matches[$index])) {
					// Another pattern matched at this position: keep longest match
					$matches[$index] = max($matches[$index], $length);
				} else {
					// Store position (key) and length (value) of match
					$matches[$index] = $length;
				}
			}
		}

		if (!count($matches)) { // No matches
			return null;
		}

		if ($this->manageAccents) {
			// Shift matches according to pairs of letters in searched text corresponding to a single Unicode character in original (displayed) text
			$matches = $this->correctIndexes($matches, $text);
		}

		$mergedMatches = array();
		$prevMatch = $prevIndex = null;
		// Merge all matches that touch or overlap each other (not considering context)
		foreach ($matches as $index => $length) {
			if ($prevMatch && $index <= $prevMatch['index'] + $prevMatch['length']) { // Current match touches previous one
				// Merge both matches into one
				$mergedMatches[$prevIndex][0]['length'] = max($index - $mergedMatches[$prevIndex][0]['index'] + $length, $mergedMatches[$prevIndex][0]['length']);
				// Store merged match to test against next
				$prevMatch = $mergedMatches[$prevIndex][0];
			} else { // No merging
				// Store current match as it is, remember it as previous match
				$mergedMatches[$prevIndex = $index] = array($prevMatch = array('index' => $index, 'length' => $length));
			}
		}
		$matches = $mergedMatches;

		$snippet = '';
		if ($markup) { // Only snippets are to be displayed
			// Merge matches (which modifies context length) until correct context length is reached
			do {
				$matchCount = count($matches);
				$chunks = array();
				// Calculate adequate chunk of text to display around each array of merged matches
				foreach ($matches as $index => $match) {
					$chunks[$index] = $this->getChunk($match, $text, $matchCount, $settings);
				}

				$prevChunk = $prevIndex = null;
				foreach ($chunks as $index => $chunk) {
					if ($prevChunk && $chunk['start'] <= $prevChunk['end'] + $settings['context']['minInterval']) {
						// Chunk of text isn't distant enough from previous one: merge both into one
						$matches[$prevIndex] = array_merge($matches[$prevIndex], $matches[$index]);
						unset($matches[$index]);
					} else {
						$prevIndex = $index;
					}
					$prevChunk = $chunk;
				}
				// Do this as long as context length is modified by previous merging operation
			} while ($this->context(count($matches), $settings) > $this->context($matchCount, $settings));

			$chunks = array();
			// Construct adequate chunk of text to display around each array of merged matches
			foreach ($matches as $index => $match) {
				$chunks[$index] = $this->getChunk($match, $text, $matchCount, $settings);
			}

			// Display all groups of merged matches surrounded by adequate context
			foreach ($chunks as $index => $chunk) {
				// Display separator except at beginning of text
				if ($chunk['start'] > 0) {
					$snippet .= $settings['snippet']['between'];
				}

				// Display context before matches
				$snippet .= $settings['snippet']['before'];
				$snippet .= mb_substr($text, $chunk['start'], $index - $chunk['start']);

				// Display all matches
				$prevMatch = null;
				foreach ($matches[$index] as $match) {
					if ($prevMatch) {
						// Display text between previous match and current one
						$snippet .= mb_substr($text, $p = $prevMatch['index'] + $prevMatch['length'], $match['index'] - $p);
					}
					// Display current match
					$snippet .= $settings['match']['before'] .
							mb_substr($text, $match['index'], $match['length']) .
							$settings['match']['after'];

					$prevMatch = $match;
				}

				// Display context after matches
				$snippet .= mb_substr($text, $p = $prevMatch['index'] + $prevMatch['length'], $chunk['end'] - $p);
				$snippet .= $settings['snippet']['after'];
			}

			// Display separator except at end of text
			if ($chunk['end'] < $textLength - 1) {
				$snippet .= $settings['snippet']['between'];
			}
		} else { // Whole text is to be displayed
			$prevIndex = 0;
			foreach ($matches as $match) {
				$snippet .= mb_substr($text, $prevIndex, $match[0]['index'] - $prevIndex) . // Text between previous match and this one
						$settings['match']['before'] .
						mb_substr($text, $match[0]['index'], $match[0]['length']) . // Matching word
						$settings['match']['after'];
				$prevIndex = $match[0]['index'] + $match[0]['length'];
			}
			$snippet .= mb_substr($text, $prevIndex); // Text after last match
		}

		return $snippet;
	}

	/**
	 * Calculates adequate length of text to display around a match or several merged matches in given text
	 * Adds context and checks that a word or number isn't split at beginning or end of chunk (if so, expand chunk)
	 * @param array $matches: positions and lengths of matching text parts
	 * @param string $text
	 * @param int $matchCount: total number of (unmerged) matches
	 * @return array: position and length of surrounding chunk 
	 */
	private function getChunk($matches, $text, $matchCount, $settings) {
		$textLength = strlen($text);

		// Define beginning of chunk: add context left of first match
		$start = max(array(0, $matches[0]['index'] - $this->context($matchCount, $settings)));
		// Define end of chunk: add context right of last match
		$end = min(array($textLength,
			$matches[count($matches) - 1]['index'] +
			$matches[count($matches) - 1]['length'] +
			$this->context($matchCount, $settings)
				));

		// If beginning is part of a word or number which extends backwards, expand chunk backwards
		if ($this->isWord($text, $start)) {
			while ($start > 0 && $this->isWord($text, $start - 1)) {
				$start--;
			}
		}
		// If end is part of a word or number which extends forwards, expand chunk forwards
		if ($this->isWord($text, $end - 1)) {
			while ($end < $textLength && $this->isWord($text, $end)) {
				$end++;
			}
		}

		return compact('start', 'end');
	}

	/**
	 * @param int $matchCount: total number of (unmerged) matches
	 * @param array $settings: settings containing snippets min and max length
	 * @return int: adequate context length to display left and right of matches, depending on given match count
	 */
	private function context($matchCount, $settings) {
		return $settings['context']['min'] + ($settings['context']['max'] - $settings['context']['min']) / $matchCount;
	}

	/**
	 * @param string $text
	 * @param int $index
	 * @return boolean: true if char at given index in given text is a word character
	 */
	private function isWord($text, $index) {
		if ($index < 0 || $index >= mb_strlen($text)) {
			return null;
		}
		return preg_match('/\w/', mb_substr($text, $index, 1));
	}

	/**
	 * @param array $matches: associates match positions to match lengths
	 * @param string $text: original text (matches have been found in de-accentuated text)
	 * @return array: given matches corrected, considering pairs of letters in searched text corresponding
	 * to a single Unicode character in original (displayed) text
	 */
	private function correctIndexes($matches, $text) {
		// Get all double-letter Unicode characters
		$accents = Accents::findDoubleAccents($text, $this->locale);
		foreach ($accents as $position => $accent) {
			foreach ($matches as $index => $length) {
				if ($index > $position) { // This match comes after this double-letter Unicode character
					// Do not keep match if it starts between the two letters
					if ($index > $position + 1) {
						// Shift match backwards since the pair of letters corresponds to a single Unicode character in original text
						$matches[$index - 1] = $matches[$index];
					}
					unset($matches[$index]);
				} else if ($index + $length > $position) { // This match overlaps this double-letter Unicode character
					if ($index + $length > $position + 1) {
						// Shorten match since the pair of letters corresponds to a single Unicode character in original text
						$matches[$index] = $matches[$index] - 1;
					} else {
						// Do not keep match if it ends between the two letters
						unset($matches[$index]);
					}
				}
			}
		}

		return $matches;
	}

}

?>
