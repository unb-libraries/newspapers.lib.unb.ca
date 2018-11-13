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

    $title = [
      "#type" => "processed_text",
      "#text" => t("Holdings"),
      "#format" => "full_html",
      "#langcode" => "en",
    ];

    $build['title'] = $title;
    $build['title']['#prefix'] = '<h2 class="issue-list-title">';
    $build['title']['#suffix'] = '</h2>';
    $build['title']['#weight'] = -100;

    $build['table']['#empty'] = 'No holdings have been added to this title yet.';

    $build['add_holdings_button'] = [
      '#type' => 'link',
      '#title' => [
        '#markup' => '<small class="glyphicon glyphicon-plus-sign"></small>' . t('Add New Holding'),
      ],
      '#url' => Url::fromRoute(
        'serial_holding.add_holding',
        [
          'node' => \Drupal::routeMatch()->getParameters()->get('node'),
        ]
      ),
      '#attributes' => [
        'class' => ['btn btn-primary icon-before'],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['type'] = $this->t('Holding Type');
    $header['coverage'] = $this->t('Coverage');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\serial_holding\Entity\SerialHolding */
    $row['id'] = $entity->id();
    $row['type'] = $entity->getHoldingType()->getName();
    $row['coverage'] = Link::createFromRoute(
      $entity->getHoldingCoverage(),
      'entity.serial_holding.edit_form',
      ['serial_holding' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
