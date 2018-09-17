<?php

namespace Drupal\digital_serial_page\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds the parent issue and title info to indexed pages.
 *
 * @SearchApiProcessor(
 *   id = "index_parent_page_info",
 *   label = @Translation("Index Parent Information for Page"),
 *   description = @Translation("Add a page's parent issue and title information to index."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class IndexPageParentPageInfo extends ProcessorPluginBase {

  /**
   * Only enabled for an index that indexes the digital_serial_page entity.
   *
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    $supported_entity_types = ['digital_serial_page'];
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
        'label' => $this->t('Parent Page ID'),
        'description' => $this->t('The parent digital Page ID'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['parent_digital_page_id'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Parent Issue ID'),
        'description' => $this->t('The parent digital Issue ID'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['parent_digital_issue_id'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Parent Digital Title ID'),
        'description' => $this->t('The parent digital title ID'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['parent_digital_title_id'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Parent Publication ID'),
        'description' => $this->t('The parent publication ID'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['parent_publication_id'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Parent Publication Title'),
        'description' => $this->t('The parent publication title'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['parent_publication_title'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Parent Issue Volume'),
        'description' => $this->t('The parent issue volume'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['parent_issue_volume'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Parent Issue Issue'),
        'description' => $this->t('The parent issue issue'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['parent_issue_issue'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Parent Issue Date'),
        'description' => $this->t('The parent issue date'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['parent_issue_date'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Parent Issue Locality'),
        'description' => $this->t('The parent issue place of publication city/etc'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['parent_issue_locality'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $entity = $item->getDatasource();
    if ($entity->getEntityTypeId() == 'digital_serial_page') {
      $page_entity = $item->getOriginalObject()->getValue();
      $issue_entity = $page_entity->getParentIssue();
      $digital_title_entity = $issue_entity->getParentTitle();
      $publication_entity = $digital_title_entity->getParentPublication();

      // Digital Issue ID.
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'parent_digital_issue_id');
      foreach ($fields as $field) {
        $field->addValue($issue_entity->id());
      }

      // Digital Page ID.
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'parent_digital_page_id');
      foreach ($fields as $field) {
        $field->addValue($page_entity->id());
      }

      // Digital Title ID.
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'parent_digital_title_id');
      foreach ($fields as $field) {
        $field->addValue($digital_title_entity->id());
      }

      // Publication ID.
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'parent_publication_id');
      foreach ($fields as $field) {
        $field->addValue($publication_entity->id());
      }

      // Issue Volume.
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'parent_publication_title');
      foreach ($fields as $field) {
        $field->addValue($publication_entity->getTitle());
      }

      // Issue Volume.
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'parent_issue_volume');
      foreach ($fields as $field) {
        $field->addValue($issue_entity->getIssueVol());
      }

      // Issue Issue.
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'parent_issue_issue');
      foreach ($fields as $field) {
        $field->addValue($issue_entity->getIssueIssue());
      }

      // Issue City Location.
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'parent_issue_locality');
      foreach ($fields as $field) {
        $field->addValue($publication_entity->get('field_place_of_publication')->first()->getLocality());
      }
    }
  }

}
