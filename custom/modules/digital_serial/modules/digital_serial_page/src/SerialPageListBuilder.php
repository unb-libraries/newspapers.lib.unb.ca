<?php

namespace Drupal\digital_serial_page;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Serial page entities.
 *
 * @ingroup digital_serial_page
 */
class SerialPageListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = 'No pages have been added to this issue yet.';
    // Add project modules.
    $build['add_pages_button'] = [
      '#attributes' => [
        'class' => ['btn btn-info btn-add'],
      ],
      '#title' => t('Add New Page'),
      '#type' => 'link',
      '#url' => Url::fromRoute(
        'entity.digital_serial_issue.add_page',
        [
          'digital_serial_issue' => \Drupal::routeMatch()->getParameters()->get('digital_serial_issue'),
        ]
      ),
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $issue = \Drupal::routeMatch()->getParameters()->get('digital_serial_issue');

    $query = $this->getStorage()->getQuery()
      ->condition('parent_issue', $issue)
      ->sort('page_sort');

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
  public function buildHeader() {
    $header['page_no'] = $this->t('Page No');
    $header['page_image'] = $this->t('Page Preview');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\digital_serial_page\SerialPage */
    $row['page_no'] = $entity->getPageNo();
    $linked_image = $entity->getLinkedStyledImage('thumbnail');
    $row['page_image'] = $linked_image->toString();

    return $row + parent::buildRow($entity);
  }

}
