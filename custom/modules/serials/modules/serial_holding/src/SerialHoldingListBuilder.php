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
    $entity_query = $this
      ->getStorage()
      ->getQuery()
      ->condition('parent_title', $title);

    $header = $this->buildHeader();

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $entity_query->pager($this->limit);
    }

    // Make the table sortable.
    $entity_query->tableSort($header);

    $uids = $entity_query->execute();

    return $this->storage->loadMultiple($uids);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    $title = [
      "#type" => "processed_text",
      "#text" => $this->t("Holdings"),
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
        '#markup' => '<span class="fas fa-paperclip fa-sm mr-1"></span>' . $this->t('Add New Holding'),
      ],
      '#url' => Url::fromRoute(
        'serial_holding.add_holding',
        [
          'node' => \Drupal::routeMatch()->getParameters()->get('node'),
        ]
      ),
      '#attributes' => [
        'class' => ['btn btn-primary my-2'],
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
    $header['institution'] = $this->t('Institution');
    $header['coverage'] = $this->t('Coverage');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $inst_term = $entity->getInstitution();
    $inst_term_name = $inst_term ? $inst_term->getName() : $this->t('None selected');
    $holding_type = $entity->getHoldingType()->getName();
    if ($holding_type == 'Microform') {
      $holding_type .= $entity->getMicroformType()
        ? " (" . $entity->getMicroformType() . ")"
        : " (" . $this->t("Undefined") . ")";
    }

    /* @var $entity \Drupal\serial_holding\Entity\SerialHolding */
    $row['id'] = $entity->id();
    $row['type'] = $holding_type;
    $row['institution'] = $inst_term_name;
    $row['coverage'] = Link::createFromRoute(
      $entity->getHoldingCoverage(),
      'entity.serial_holding.edit_form',
      ['serial_holding' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
