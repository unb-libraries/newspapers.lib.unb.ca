<?php

namespace Drupal\newspapers_core\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds additional information to indexed publications.
 *
 * @SearchApiProcessor(
 *   id = "index_parent_title_info",
 *   label = @Translation("Index Additional Information for a Publication"),
 *   description = @Translation("Add a additional title information to index."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class IndexAdditionalTitleInfo extends ProcessorPluginBase {

  /**
   * Only enabled for an index that indexes the publication entity.
   *
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() == 'node') {
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
        'label' => $this->t('Publication Years'),
        'description' => $this->t('Years this publication was published.'),
        'type' => 'integer',
        'is_list' => TRUE,
        'processor_id' => $this->getPluginId(),
      ];
      $properties['years_published'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Holdings'),
        'description' => $this->t('List of UNB Library Holdings available for this publication.'),
        'type' => 'string',
        'is_list' => TRUE,
        'processor_id' => $this->getPluginId(),
      ];
      $properties['holdings'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $node = $item->getOriginalObject()->getValue();
    if ($node instanceof NodeInterface) {
      if ($node->bundle() == 'publication') {

        // Years published.
        $fields = $this->getFieldsHelper()
          ->filterForPropertyPath($item->getFields(), NULL, 'years_published');
        foreach ($fields as $field) {
          if (!empty($node->get('field_first_issue_search_date')->date) && !empty($node->get('field_last_issue_search_date')->date)) {
            $pub_start_year = $node->get('field_first_issue_search_date')->date->format('Y');
            $pub_end_year = $node->get('field_last_issue_search_date')->date->format('Y');
            for ($year = $pub_start_year; $year <= $pub_end_year; $year++) {
              $field->addValue($year);
            }
          }
        }

        // Holdings.
        $fields = $this->getFieldsHelper()
          ->filterForPropertyPath($item->getFields(), NULL, 'holdings');
        $node_id = $node->id();
        $holdings = _newspapers_core_get_publication_holdings($node_id);
        if ($holdings) {
          foreach ($fields as $field) {
            // Only show online holding item once.
            $online = FALSE;
            foreach ($holdings as $type => $type_holdings) {
              if ($type == 'digital') {
                if (!$online) {
                  // Digital title holdings display as online.
                  $field->addValue('online');
                  $online = TRUE;
                }
              }
              elseif ($type == 'online') {
                if (!$online) {
                  $field->addValue($type);
                  $online = TRUE;
                }
              }
              else {
                $field->addValue($type);
              }
            }
          }
        }
      }
    }
  }

}
