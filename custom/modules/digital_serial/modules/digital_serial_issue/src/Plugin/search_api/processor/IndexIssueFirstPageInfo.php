<?php

namespace Drupal\digital_serial_issue\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\EntityProcessorProperty;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\Utility\Utility;

/**
 * Adds an issue's first-page id, image URI and page entity to the index.
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
    elseif ($datasource->getEntityTypeId() == 'digital_serial_issue') {
      // Exposed as an entity so its nested properties can be indexed. Notably,
      // first_page:page_image is a real image field, allowing Search API Views
      // to render it via entity field rendering (e.g. with an image style).
      // This must be attached to the issue datasource, rather than being
      // datasource-independent: SearchApiEntityField::getItems() only renders
      // fields whose datasource matches the result row's datasource.
      $definition = [
        'label' => $this->t('First Page'),
        'description' => $this->t("The issue's first page entity"),
        'type' => 'entity:digital_serial_page',
        'processor_id' => $this->getPluginId(),
      ];
      $property = new EntityProcessorProperty($definition);
      $property->setEntityTypeId('digital_serial_page');
      $properties['first_page'] = $property;
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

    // Search API invokes this processor once per extraction pass in which it
    // provides an indexed field: once for datasource-independent fields
    // (first_page_id, first_page_image_uri) and once for the issue datasource
    // (first_page:*). Only fill fields that are still empty, so values are not
    // added twice (Solr rejects duplicates in single-valued fields). Use
    // getFields(FALSE) to avoid re-triggering field extraction from within it.
    $all_fields = $item->getFields(FALSE);
    $helper = $this->getFieldsHelper();
    $id_fields = array_filter(
      $helper->filterForPropertyPath($all_fields, NULL, 'first_page_id'),
      fn($field) => empty($field->getValues())
    );
    $uri_fields = array_filter(
      $helper->filterForPropertyPath($all_fields, NULL, 'first_page_image_uri'),
      fn($field) => empty($field->getValues())
    );

    // Collect empty index fields based on the (datasource-specific) first_page
    // entity property, grouped by their nested property path ('' for the
    // entity itself).
    $fields_to_extract = [];
    foreach ($all_fields as $field) {
      if ($field->getDatasourceId() !== $item->getDatasourceId() || !empty($field->getValues())) {
        continue;
      }
      [$direct, $nested] = Utility::splitPropertyPath($field->getPropertyPath(), FALSE);
      if ($direct === 'first_page') {
        $fields_to_extract[$nested][] = $field;
      }
    }

    // Nothing left to fill: skip the first page lookup entirely.
    if (!$id_fields && !$uri_fields && !$fields_to_extract) {
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

    foreach ($id_fields as $field) {
      $field->addValue((int) $first_page->id());
    }

    // The image file may be missing even when a page exists; skip the URI then.
    $image_file = $first_page->getPageImage();
    if (!empty($image_file)) {
      foreach ($uri_fields as $field) {
        $field->addValue($image_file->getFileUri());
      }
    }

    // Fields on the entity itself receive the entity object. Search API Views
    // relies on this to load the page when rendering nested fields.
    if (isset($fields_to_extract[''])) {
      foreach ($fields_to_extract[''] as $field) {
        $field->setValues([$first_page]);
      }
      unset($fields_to_extract['']);
    }

    if (!empty($fields_to_extract)) {
      $helper->extractFields(
        $first_page->getTypedData(),
        $fields_to_extract,
        $item->getLanguage()
      );
    }
  }

}
