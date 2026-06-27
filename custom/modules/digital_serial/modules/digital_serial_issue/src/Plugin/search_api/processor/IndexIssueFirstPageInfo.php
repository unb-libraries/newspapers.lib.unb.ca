<?php

namespace Drupal\digital_serial_issue\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds an issue's first-page id and image URI to the index.
 *
 * The issue browser shows each issue's first page (cover) and links directly to
 * it. Resolving the first page requires a reverse lookup into the issue's child
 * pages, which is expensive at query time. This processor performs that lookup
 * once at index time so the browse view can render entirely from precomputed
 * Solr data with no per-row entity/file loads.
 *
 * @SearchApiProcessor(
 *   id = "index_issue_first_page_info",
 *   label = @Translation("Index First Page Information for Issue"),
 *   description = @Translation("Add an issue's first page id and image URI to the index."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class IndexIssueFirstPageInfo extends ProcessorPluginBase {

  /**
   * Only enabled for an index that indexes the digital_serial_issue entity.
   *
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    $supported_entity_types = ['digital_serial_issue'];
    foreach ($index->getDatasources() as $datasource) {
      if (in_array($datasource->getEntityTypeId(), $supported_entity_types)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('First Page ID'),
        'description' => $this->t("The entity ID of the issue's first page"),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['first_page_id'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('First Page Image URI'),
        'description' => $this->t("The file URI of the issue's first page image"),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['first_page_image_uri'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $datasource = $item->getDatasource();
    if (empty($datasource) || $datasource->getEntityTypeId() != 'digital_serial_issue') {
      return;
    }

    $issue_entity = $item->getOriginalObject()->getValue();
    if (empty($issue_entity)) {
      return;
    }

    // Resolve the first page (lowest page_sort). Issues without pages are
    // skipped, leaving these fields empty.
    $first_page = $issue_entity->getFirstPage();
    if (empty($first_page)) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'first_page_id');
    foreach ($fields as $field) {
      $field->addValue((int) $first_page->id());
    }

    // The image file may be missing even when a page exists; skip the URI then.
    $image_file = $first_page->getPageImage();
    if (!empty($image_file)) {
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'first_page_image_uri');
      foreach ($fields as $field) {
        $field->addValue($image_file->getFileUri());
      }
    }
  }

}
