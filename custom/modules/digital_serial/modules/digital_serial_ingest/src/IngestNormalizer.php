<?php

namespace Drupal\digital_serial_ingest;

use Drupal\digital_serial_issue\Entity\SerialIssueInterface;
use Drupal\digital_serial_page\Entity\SerialPageInterface;
use Drupal\digital_serial_title\Entity\SerialTitleInterface;
use Drupal\file\FileInterface;

/**
 * Builds the JSON documents the ingest endpoints return.
 *
 * The shapes are the contract with tools/nbnp-ingest; add fields freely, but
 * renaming or removing one breaks the tool.
 */
class IngestNormalizer {

  /**
   * Reports which generated assets a page has.
   *
   * @var callable(\Drupal\digital_serial_page\Entity\SerialPageInterface): array{dzi: bool, pdf: bool}
   */
  protected $assetState;

  /**
   * Constructs an IngestNormalizer.
   *
   * @param callable|null $asset_state
   *   Returns ['dzi' => bool, 'pdf' => bool] for a page. Defaults to checking
   *   the files the page viewer would use; tests pass their own.
   */
  public function __construct(?callable $asset_state = NULL) {
    $this->assetState = $asset_state ?? [static::class, 'viewerAssetState'];
  }

  /**
   * Normalizes an issue and its pages.
   *
   * @param \Drupal\digital_serial_issue\Entity\SerialIssueInterface $issue
   *   The issue.
   * @param \Drupal\digital_serial_page\Entity\SerialPageInterface[] $pages
   *   The issue's pages, in any order.
   *
   * @return array
   *   The issue document.
   */
  public function issue(SerialIssueInterface $issue, array $pages): array {
    $page_documents = array_map([$this, 'page'], array_values($pages));
    // A stable order, so the tool and people reading the output agree.
    usort($page_documents, fn(array $a, array $b) => [$a['page_sort'], $a['page_no'], $a['id']] <=> [$b['page_sort'], $b['page_no'], $b['id']]);

    return [
      'id' => (int) $issue->id(),
      'uuid' => $issue->uuid(),
      'title_id' => self::intOrNull($issue->get('parent_title')->target_id),
      'issue_title' => self::stringValue($issue, 'issue_title'),
      'volume' => self::stringValue($issue, 'issue_vol'),
      'issue' => self::stringValue($issue, 'issue_issue'),
      'edition' => self::stringValue($issue, 'issue_edition'),
      'date' => $issue->get('issue_date')->value,
      'missing_pages' => self::stringValue($issue, 'issue_missingp'),
      'errata' => self::stringValue($issue, 'issue_errata'),
      'language' => self::stringValue($issue, 'issue_language'),
      'media' => self::stringValue($issue, 'issue_media'),
      'pages' => $page_documents,
    ];
  }

  /**
   * Normalizes a page.
   *
   * @param \Drupal\digital_serial_page\Entity\SerialPageInterface $page
   *   The page.
   *
   * @return array
   *   The page document.
   */
  public function page(SerialPageInterface $page): array {
    $file = $page->get('page_image')->entity;
    $assets = ($this->assetState)($page);
    return [
      'id' => (int) $page->id(),
      'uuid' => $page->uuid(),
      'page_no' => (string) $page->getPageNo(),
      'page_sort' => (string) $page->getPageSort(),
      'image_path' => $file instanceof FileInterface ? self::publicRelativePath($file->getFileUri()) : NULL,
      'asset_basename' => $file instanceof FileInterface ? pathinfo($file->getFilename(), PATHINFO_FILENAME) : NULL,
      'image_sha256' => $page->getImageSha256(),
      'has_dzi' => $assets['dzi'],
      'has_pdf' => $assets['pdf'],
    ];
  }

  /**
   * Normalizes a digital title.
   *
   * @param \Drupal\digital_serial_title\Entity\SerialTitleInterface $title
   *   The title.
   *
   * @return array
   *   The title document.
   */
  public function title(SerialTitleInterface $title): array {
    return [
      'id' => (int) $title->id(),
      'uuid' => $title->uuid(),
      'name' => $title->getParentPublication()?->label(),
      'label' => $title->label(),
    ];
  }

  /**
   * Reports the generated assets the page viewer would find for a page.
   *
   * Uses the page's own lookups, which accept every location assets have
   * been stored in over the years. Those lookups derive asset names by
   * replacing '.jpg', so other image names are reported as having none
   * rather than mistaking the image for its tiles. Tiles count only with
   * their _files directory, which an interrupted copy may lack.
   *
   * @param \Drupal\digital_serial_page\Entity\SerialPageInterface $page
   *   The page.
   *
   * @return array{dzi: bool, pdf: bool}
   *   Whether each asset exists.
   */
  public static function viewerAssetState(SerialPageInterface $page): array {
    $file = $page->getPageImage();
    if (!$file instanceof FileInterface || !str_ends_with($file->getFilename(), '.jpg')) {
      return ['dzi' => FALSE, 'pdf' => FALSE];
    }
    $dzi = $page->getDziUri();
    return [
      'dzi' => !empty($dzi) && is_dir(substr($dzi['file'], 0, -strlen('.dzi')) . '_files'),
      'pdf' => !empty($page->getPdfUri()),
    ];
  }

  /**
   * Converts a public:// URI to a path relative to the public files directory.
   */
  protected static function publicRelativePath(string $uri): string {
    return str_starts_with($uri, 'public://') ? substr($uri, strlen('public://')) : $uri;
  }

  /**
   * Gets a string field value, with empty and missing values as ''.
   */
  protected static function stringValue(SerialIssueInterface $issue, string $field): string {
    return (string) ($issue->get($field)->value ?? '');
  }

  /**
   * Casts an ID that may be missing.
   */
  protected static function intOrNull(mixed $value): ?int {
    return $value === NULL || $value === '' ? NULL : (int) $value;
  }

}
