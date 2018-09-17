<?php

namespace Drupal\digital_serial_page;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use InvalidArgumentException;

/**
 * Serial Page HOCR parsing. Used to generate OpenSeadragon overlays.
 *
 * Most of this class originally written by Islandora/UPEI as D7 include:
 *
 * https://github.com/Islandora/islandora_ocr/blob/7.x/includes/hocr.inc
 */
class SerialPageHocr {

  const MINIMUM_TESSERACT_VERSION = 3;

  /**
   * The HOCR file.
   *
   * @var \DOMDocument
   */
  public $doc;

  /**
   * The XPath for querying the HOCR file.
   *
   * @var \DOMXPath
   */
  public $xpath;

  /**
   * Instantiate an HOCR object.
   *
   * @param string $hocr
   *   The HOCR file contents.
   *
   * @throws \InvalidArgumentException
   */
  public function __construct($hocr) {
    if (empty($hocr)) {
      throw new InvalidArgumentException('Attempted to instantiate the HOCR class without a valid HOCR file.');
    }
    $this->doc = new DOMDocument('1.0', 'UTF-8');
    $this->doc->loadHTML($hocr);
    $this->xpath = new DOMXPath($this->doc);
    $this->xpath->registerNamespace("php", "http://php.net/xpath");
    $this->xpath->registerPhpFunctions();
  }

  /**
   * Checks if the given HOCR file is valid.
   *
   * At the moment this only checks if the version is supported but may do more
   * in the future.
   *
   * @param string $file
   *   The absolute path to the HOCR file.
   *
   * @return bool
   *   TRUE if the HOCR file is valid, FALSE otherwise.
   */
  public static function isValid($file) {
    if (file_exists($file)) {
      $creator = self::getCreator($file);
      if ($creator) {
        if (strrpos($creator, 'tesseract') === 0) {
          $version = str_replace(['tesseract', ' '], '', $creator);
          if ($version > self::MINIMUM_TESSERACT_VERSION) {
            return TRUE;
          };
        }
        return TRUE;
      }
      return FALSE;
    }
    return FALSE;
  }

  /**
   * Gets the OCR engine creator.
   *
   * @param string $file
   *   The absolute path to the HOCR file.
   *
   * @return bool|string
   *   The version if successful, FALSE otherwise.
   */
  public static function getCreator($file) {
    $doc = simplexml_load_file($file);
    if ($doc) {
      $doc->registerXPathNamespace('ns', 'http://www.w3.org/1999/xhtml');
      $content_attributes = $doc->xpath('/ns:html/ns:head/ns:meta[@name="ocr-system"]/@content');
      $creator = (string) reset($content_attributes);
      return empty($creator) ? FALSE : $creator;
    }
    return FALSE;
  }

  /**
   * Gets the dimensions for the given page.
   *
   * Almost all HOCR documents will only have one page. Page indexes start from
   * zero.
   *
   * @param int $page_number
   *   (optional) The number of page to get, defaults to 0.
   *
   * @return mixed
   *   An associative array:
   *   - width: The width of the page.
   *   - height: The height of the page.
   */
  public function getPageDimensions($page_number = 0) {
    $nodes = $this->findClassNodes('ocr_page');
    $pages = $this->getProperties($nodes);
    foreach ($pages as $page) {
      // For things that have page numbers set return the match. Otherwise
      // return the first page in other pages array. It's to be noted that
      // ppageno is not an explicitly required element within an ocr_page
      // element. Similarly, with the hOCR that gets generated with tesseract
      // the page number will always be 0.
      if ((isset($page['ppageno']) && $page['ppageno'] == $page_number) || !isset($page['ppageno'])) {
        return $this->getPropertyDimensions($page);
      }
    }
    return FALSE;
  }

  /**
   * Find all nodes of a given classes.
   *
   * @param mixed $classes
   *   The class/classes of the elements to find, one or more of the following:
   *   'ocr_page', 'ocr_carea', 'ocr_par', 'ocr_line', 'ocrx_word'.
   *   If no classes are given all nodes are returned.
   *
   * @return \DOMNodeList
   *   All matching elements containing the given term and classes.
   */
  protected function findClassNodes($classes) {
    $class_predicate = $this->getClassQueryPredicate($classes);
    return $this->xpath->query("//node(){$class_predicate}");
  }

  /**
   * Gets the classes attribute predicate.
   *
   * @param mixed $classes
   *   The class/classes of the elements to find, one or more of the following:
   *   'ocr_page', 'ocr_carea', 'ocr_par', 'ocr_line', 'ocrx_word'.
   *   If no classes are given all nodes are returned.
   *
   * @return string
   *   A XPath predicate that limits the query by the given class attributes.
   */
  protected function getClassQueryPredicate($classes) {
    $classes = is_array($classes) ? $classes : [$classes];
    $map = function ($class) {
      return "@class = '{$class}'";
    };
    $classes = array_map($map, $classes);
    $predicate = implode(' or ', $classes);
    return "[$predicate]";
  }

  /**
   * Gets the class properties for each of the given nodes.
   *
   * @param \DOMNodeList $nodes
   *   An array of DOMNode elements that have a class attribute.
   *
   * @return array
   *   An array of the properities for each given node.
   *
   * @see HOCR::getClassProperties()
   */
  protected function getProperties(DOMNodeList $nodes) {
    $results = [];
    foreach ($nodes as $node) {
      $results[] = $this->getClassNodeProperties($node);
    }
    return $results;
  }

  /**
   * Gets all the defined properties for the given class element.
   *
   * Gets nothing for non class elements.
   *
   * @param \DOMElement $element
   *   The element.
   *
   * @return array
   *   An associative array of properties as follows:
   *   - id: The id of the given element.
   *   - class: The class of the given element, 'ocrx_word', 'ocr_line', etc.
   *   - dir: The direction of the written text either 'ltr' or 'rtl'.
   *   - bbox: The bounding box  of the given element.
   *     - left: The left most point of the element in pixels.
   *     - top: The top most point of the element in pixels.
   *     - right: The right most point of the element in pixels.
   *     - bottom: The bottom most point of the element in pixels.
   *   - ppageno: The number of the page if the class is 'ocr_page'.
   */
  protected function getClassNodeProperties(DOMElement $element) {
    $bbox = NULL;
    if ($element->hasAttribute('title')) {
      $matches = [];
      $title = $element->getAttribute('title');
      $pattern = '/bbox ([0-9]*) ([0-9]*) ([0-9]*) ([0-9]*)/';
      if (preg_match($pattern, $title, $matches)) {
        $bbox = [
          'left' => intval($matches[1]),
          'top' => intval($matches[2]),
          'right' => intval($matches[3]),
          'bottom' => intval($matches[4]),
        ];
      }
      $pattern = '/ppageno ([0-9]*)/';
      if (preg_match($pattern, $title, $matches)) {
        $ppageno = isset($matches[1]) ? intval($matches[1]) : NULL;
      }
    }
    // Some HOCR implementations don't provide unique IDs for words. In this
    // case let's use the node path as it's guaranteed to be unique. These IDs
    // are currently not used in any of the theme layer code.
    $properties = array_filter(
      [
        'id' => $element->hasAttribute('id') ? $element->getAttribute('id') : $element->getNodePath(),
        'class' => $element->hasAttribute('class') ? $element->getAttribute('class') : NULL,
        'dir' => $element->hasAttribute('dir') ? $element->getAttribute('dir') : NULL,
        'bbox' => $bbox,
      ]
    );
    if (isset($ppageno)) {
      $properties['ppageno'] = $ppageno;
    }
    return $properties;
  }

  /**
   * Gets the dimensions of the given class node properties.
   *
   * If the properties don't contain 'bbox' then 0 is returned
   * for the dimensions.
   *
   * @param array $properties
   *   An associative array of the node properties to evaluate.
   *
   * @return array
   *   An associative array containing the properties dimensions.
   *   - width: The width of the page.
   *   - height: The height of the page.
   */
  protected function getPropertyDimensions(array $properties) {
    $width = 0;
    $height = 0;
    if (isset($properties['bbox'])) {
      $bbox = $properties['bbox'];
      $width = $bbox['right'] - $bbox['left'];
      $height = $bbox['bottom'] - $bbox['top'];
    }
    return ['width' => $width, 'height' => $height];
  }

  /**
   * Searches for terms in the HOCR.
   *
   * @param array $terms
   *   An array of terms to search for.
   * @param array $options
   *   Options to modify the text selection of the search:
   *   - case_sensitive: Defaults to FALSE.
   *   - classes: The classes of elements to return, expecting one or more  of
   *     the following: 'ocr_page', 'ocr_carea', 'ocr_par', 'ocr_line',
   *     'ocrx_word'.
   *   - match_exact_phrase: Only match the given terms if they occur in the
   *     same order.
   *
   * @return array
   *   The properties for each term and its respective classes.
   */
  public function search(array $terms, array $options = []) {
    $options += [
      'classes' => ['ocrx_word'],
      'case_sensitive' => FALSE,
    ];
    $nodes = $this->findTermNodes($terms, $options);
    return $nodes ? $this->getProperties($nodes) : [];
  }

  /**
   * Find instances of the given term in document.
   *
   * @param array $terms
   *   The terms to search for.
   * @param array $options
   *   Options to modify the text selection of the search:
   *   - case_sensitive: Defaults to FALSE.
   *   - classes: The classes of elements to return, expecting one or more  of
   *     the following: 'ocr_page', 'ocr_carea', 'ocr_par', 'ocr_line',
   *     'ocrx_word'.
   *
   * @return \DOMNodeList
   *   All matching elements containing the given term and classes.
   */
  protected function findTermNodes(array $terms, array $options) {
    if (!empty($terms)) {
      $term_predicate = $this->getTermQueryPredicate($terms, $options);
      if (!empty($term_predicate)) {
        $query = "//*$term_predicate";
        if (!empty($options['classes'])) {
          $class_predicate = $this->getClassQueryPredicate($options['classes']);
          $query .= "/ancestor-or-self::node(){$class_predicate}";
        }
        return $this->xpath->query($query);
      }
    }
    return new DOMNodeList();
  }

  /**
   * Gets the text node (term) values predicate.
   *
   * @param array $terms
   *   The terms used to generate the predicate.
   * @param array $options
   *   Options to modify the text selection of the search:
   *   - case_sensitive: Defaults to FALSE.
   *   - classes: The classes of elements to return, expecting one or more  of
   *     the following: 'ocr_page', 'ocr_carea', 'ocr_par', 'ocr_line',
   *     'ocrx_word'.
   *
   * @return string
   *   A XPath predicate that limits the query by the given terms.
   */
  protected function getTermQueryPredicate(array $terms, array $options) {
    if (isset($options['match_exact_phrase']) && $options['match_exact_phrase']) {
      return $this->getTermPhraseQueryPredicate($terms, $options);
    }
    else {
      $get_term_predicate = $this->getMatchTermExpressionFunction($options);
      $terms = array_map([$this, $get_term_predicate], $terms);
      $predicate = implode(' or ', $terms);
      return "[$predicate]";
    }
  }

  /**
   * Generates a phrase matching predicates that will only match nodes.
   *
   * Takes the form of:
   * [text() = 'term_1' and following::*[1..n][text() =
   * 'term_2..n']]/preceding::node()[1]/following::[position() <= n]
   *
   * @param array $terms
   *   The phrase, assumed to be in order phrase.
   * @param array $options
   *   Options to modify the text selection of the search:
   *   - case_sensitive: Defaults to FALSE.
   *
   * @return string
   *   The predicate that will match the given phrase.
   */
  protected function getTermPhraseQueryPredicate(array $terms, array $options) {
    $phrase_predicate = [];
    $get_term_predicate = $this->getMatchTermExpressionFunction($options);
    foreach ($terms as $i => $term) {
      $term = $this->{$get_term_predicate}($term, 'self::node()');
      $phrase_predicate[] = "following::text()[normalize-space(self::node())][$i][{$term}]";
    }
    // The first predicate acts as an anchor, which selects the first node
    // in the given phrase if all other terms in the phrase follow it in
    // the correct order.
    $phrase_predicate[0] = $this->{$get_term_predicate}($terms[0]);
    $phrase_predicate = implode(' and ', $phrase_predicate);
    $count = count($terms);
    $predicate = "[{$phrase_predicate}]";
    if ($count > 1) {
      $predicate .= "/preceding::node()[1]/following::text()[normalize-space(self::node())][position() <= {$count}]";
    }
    return $predicate;
  }

  /**
   * Get the term predicate function based on the search options.
   *
   * @param array $options
   *   Options to modify the text selection of the search:
   *   - case_sensitive: Defaults to FALSE.
   *
   * @return string
   *   The member function to use for creating term predicates.
   */
  protected function getMatchTermExpressionFunction(array $options) {
    $case_sensitive = isset($options['case_sensitive']) && $options['case_sensitive'];
    return $case_sensitive ? 'getCaseSensitiveMatchTermExpression' : 'getCaseInsensitiveMatchTermExpression';
  }

  /**
   * Generates a XPath predicate that matches the given term exactly.
   *
   * @param string $term
   *   The term to match.
   * @param string $node
   *   The node type to match.
   *
   * @return string
   *   The XPath predicate to match the given term exactly.
   */
  protected function getCaseSensitiveMatchTermExpression($term, $node = 'text()') {
    return "contains($node, '{$term}')";
  }

  /**
   * Generates a XPath predicate performs a case-insensitive match.
   *
   * @param string $term
   *   The term to match.
   * @param string $node
   *   The node type to match.
   *
   * @return string
   *   The XPath predicate to do a case-insensitive match on the given term.
   */
  protected function getCaseInsensitiveMatchTermExpression($term, $node = 'text()') {
    $term = mb_strtolower($term);
    return "contains(php:functionString('mb_strtolower', $node), '{$term}')";
  }

}
