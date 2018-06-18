<?php

namespace Drupal\serial_holding;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Serial holding entities.
 *
 * @ingroup serial_holding
 */
class SerialHoldingListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function load() {
    $title = \Drupal::routeMatch()->getParameters()->get('node');
    $query = $this->getStorage()->getQuery()
      ->condition('parent_title', $title);

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    $entity_ids = $query->execute();
    return $this->storage->loadMultiple($entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = 'No holdings have been added to this title yet.';

    $build['add_holdings_button'] = [
      '#type' => 'link',
      '#title' => t('Add New Holding'),
      '#url' => Url::fromRoute(
        'serial_holding.add_holding',
        [
          'node' => \Drupal::routeMatch()->getParameters()->get('node'),
        ]
      ),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\serial_holding\Entity\SerialHolding */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.serial_holding.edit_form',
      ['serial_holding' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
