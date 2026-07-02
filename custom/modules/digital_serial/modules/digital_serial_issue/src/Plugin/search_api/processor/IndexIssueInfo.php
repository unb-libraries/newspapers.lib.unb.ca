<?php

namespace Drupal\digital_serial_issue\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds the parent issue and title info to indexed issues.
 *
 * @SearchApiProcessor(
 *   id = "index_issue_publication_facet_info",
 *   label = @Translation("Index publication information for Issue facets + title"),
 *   description = @Translation("Make supplementary publication information available to Search API > Issues index."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class IndexIssueInfo extends ProcessorPluginBase {

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
        'label' => $this->t('Facet: Publication Year'),
        'description' => $this->t('Publication Year for Issues browser facet.'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['issue_pub_year'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Facet: Publication Decade'),
        'description' => $this->t('Publication Decade for Issues browser facet.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['issue_pub_decade'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Parent Issue Publication Title'),
        'description' => $this->t('The parent issue publication title'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['parent_iss_publication_title'] = new ProcessorProperty($definition);
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

    $digital_title_entity = $issue_entity->getParentTitle();
    $publication_entity = $digital_title_entity->getParentPublication();

    if (!empty($digital_title_entity) && !empty($publication_entity)) {
      // Publication Year.
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'issue_pub_year');
      foreach ($fields as $field) {
        $field->addValue((int) $issue_entity->getYear());
      }

      // Publication Decade.
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'issue_pub_decade');
      foreach ($fields as $field) {
        $decade = floor($issue_entity->getYear() / 10) * 10;
        $field->addValue($decade . 's');
      }

      // Issue Parent Title.
        $fields = $this->getFieldsHelper()
          ->filterForPropertyPath($item->getFields(), NULL, 'parent_iss_publication_title');
        foreach ($fields as $field) {
          $field->addValue($publication_entity->getTitle());
        }
    }
  }

}
